# CLAUDE.md — saxs-a-fold

Web application for SAXS-based protein structure analysis: combines experimental Small-Angle X-ray Scattering (SAXS) data with computationally-predicted structures (AlphaFold, MD trajectories) to identify the best-fit ensemble.

Live site: https://saxsafold.genapp.rocks  
Source: https://github.com/ehb54/saxsafold  
Active dev branch: `dev`

---

## Working directory

Always clone/work in `~/claude/saxsafold` (NOT `~/saxsafold`, which is the user's own install). The repo is already cloned there.

---

## Stack

| Layer | Technology |
|---|---|
| Framework | [GenApp](https://genapp.rocks) — handles UI, job queuing, file management |
| Server language | PHP 7+ (primary), Perl (structural calcs), Python (utilities) |
| Visualization | Plotly.js via server-generated JSON |
| Database | MongoDB (job history, user management) |
| Container | Docker / Ubuntu 20.04 |
| Compute tools | WAXSiS, CRYSOL 2.8.4 + 3.2.1, ATSAS suite, US-SOMO, mdconvert |

---

## Directory structure

```
bin/               Core PHP scripts + library classes (all executable logic lives here)
  calcs/           Perl/Python scripts called by PHP (P(r), PDB utilities, MD)
  utils/           Shell utilities (xvfb-run-safe, somoinit)
modules/           GenApp module definitions (JSON): one per workflow step
files/             Static HTML (acknowledgements, disclaimer)
pngs/              UI icons
add/               Docs + SCSS assets
dockerfile/        Docker build files
menu.json          Top-level menu structure
directives.json.template  GenApp app-level config (paths, ports)
```

---

## Execution model

Every workflow step is a standalone PHP CLI script in `bin/`. GenApp calls it as:

```
php bin/somemodule.php '{"_project":"proj1","_logon":"user","_base_directory":"/...","field1":"val",...}'
```

- Input: a single JSON argument decoded into `$input`
- Output: `echo json_encode($output)` at end (or `error_exit()` / `json_exit()` earlier)
- Real-time UI updates: `$ga->tcpmessage([...])` sends incremental JSON over TCP socket
- Progress text: `progress_text("message")` — sends a blue `<h5>` banner to the UI

### Standard script preamble

```php
#!/usr/local/bin/php
<?php
{};  // ← required empty block (GenApp parsing safeguard, must be present)

$json_input = $argv[1];
$input      = json_decode( $json_input );
$output     = (object)[];

include "genapp.php";
include "datetime.php";
include "sas.php";
$sas = new SAS( false );
$ga  = new GenApp( $input, $output );

$fdir     = preg_replace( "/^.*\/results\//", "results/", $input->_base_directory );
$base_dir = preg_replace( '/^.*\//', '', $input->_base_directory );
$logon    = $input->_logon;

include_once "common.php";
$cgstate = new cgrun_state();
```

The working directory when a script runs is the project's data directory (where `state.json` lives), so all file paths are relative to that.

---

## Core classes

### `SAS` (`bin/sas.php`, ~3000 lines)

Central class for SAXS data management and Plotly figure construction.

**Constructor:** `new SAS( $debug = false, $exit_on_error = true )`  
With `SAS(false)` errors return `false` silently instead of calling `exit`.

**Internal stores:**
- `$data` — named data store: each entry has `->x`, `->y`, optionally `->error_y`, `->type` (`PLOT_IQ=0` or `PLOT_PR=1`)
- `$plots` — named Plotly figure objects; traces have `->name` baked at `add_plot` time

**Critical SAS behaviors to keep in mind:**
- `add_plot($plotname, $dataname)` — bakes `->name` into the plot trace at call time. Subsequent `rename_data()` does **not** update trace names already in a plot.
- `rename_data($from, $to)` — renames the **data store key only**; does not touch any plot trace `->name`.
- `remove_plot_data($plotname, $tracename)` — removes by trace `->name` match, leaves data store intact.
- `save_data_csv($names, $file, $mw, $regexp, $replacement)` — applies `preg_replace($regexp, $replacement, $name)` to produce the CSV column header for each name. This is a **full replacement** (the matched portion is replaced by `$replacement`), not just a strip.
- `create_plot_from_plot()` — deep-copies via `unserialize(serialize(...))` and creates data store entries with PHP references (`&$v->x`, `&$v->y`) into the copied plot traces.
- `nnls($target, $names, $fitname, &$results)` — returns only non-zero-weight components in `$results`, keyed by data name.

**Key methods by category:**

| Category | Methods |
|---|---|
| Data I/O | `load_file`, `load_somo_csv_file`, `save_file`, `save_fit`, `save_data_csv`, `save_data_csv_tr` |
| Data management | `rename_data`, `regex_rename_data`, `copy_data`, `remove_data`, `remove_data_if_exists`, `data_name_exists`, `data_names($regexp)` |
| Plot management | `create_plot`, `create_plot_from_plot`, `add_plot`, `add_plot_residuals`, `remove_plot_data`, `remove_plot`, `plot`, `plot_names` |
| Plot styling | `plot_trace_options`, `plot_options`, `annotate_plot`, `recolor_plot`, `rename_curve` |
| Analysis | `interpolate`, `scale_nchi2`, `rmsd`, `nchi2`, `calc_residuals`, `rmsd_residuals`, `nnls`, `compute_p_value` |
| P(r) | `compute_pr`, `compute_pr_many`, `extend_pr`, `norm_pr`, `compute_rg_from_pr` |
| Utilities | `significant_digits`, `common_grids`, `data_summary`, `dump_data`, `compare_data` |

**Constants:**
```php
SAS::PLOT_IQ    = 0
SAS::PLOT_PR    = 1
SAS::PLOTLY_COLORS   // 10-color palette (muted blue, safety orange, ...)
SAS::PLOT_IQ_XAXIS_TITLE = "q [Å⁻¹]"
SAS::PLOT_PR_XAXIS_TITLE = "Distance [Å]"
```

---

### `cgrun_state` (`bin/common.php`)

JSON state persistence for a project run.

```php
$cgstate = new cgrun_state();          // loads state.json from cwd
$cgstate->state->somekey = $value;     // mutate
$cgstate->save();                      // writes back to state.json (chmod 0660)
$cgstate->init();                      // wipes and saves empty state
$cgstate->dump();                      // returns pretty JSON string
```

State is a plain PHP object — all keys are dynamic. Key state fields written across modules:

| Key | Set by | Contains |
|---|---|---|
| `loaded` | defineproject | bool — project is initialized |
| `output_load` | loadstructure | `->iqplot`, `->mw`, `->name` (PDB base), `->rg` |
| `output_loadsaxs` | loadsaxs | `->iqplot` |
| `output_final` | finalmodel | `->iqplotwaxsis`, `->hist`, etc. |
| `iq_waxsis_nnlsresults` | finalmodel | object keyed by trace name → NNLS weight |
| `iq_waxsis_nnlsresults_colors` | finalmodel | object keyed by trace name → hex color |
| `waxsis_load_convergence` | loadstructure | `'normal'`/`'thorough'`/`'quick'` |
| `waxsis_final_pdb_names` | finalmodel | object: frame → PDB path |
| `mmcframecount` | runmmc | total MC frames |
| `pr_nnlsresults`, `prwe_nnlsresults` | computeiqpr | NNLS results keyed by trace name |

---

### `GenApp` (`bin/genapp.php`)

Framework communication class.

```php
$ga = new GenApp( $input, $output );

$ga->tcpmessage( [ 'key' => $value ] );          // push incremental UI update
$ga->tcptextarea( "text" );                       // append to the log textarea
$ga->tcpdebugjson( '$label', $obj );              // debug dump JSON to textarea
$ga->tcpmessagebox( [ "icon" => "...", "text" => "..." ] );  // modal dialog (fire-and-forget)
$ga->tcpquestion( $questionobj, $timeout );       // blocking modal, returns JSON response
```

**Dialog pattern (question_prior_results):**  
Defined in `remove.php`, included as needed. Prompts user about prior results before clearing them. Takes a callback `$restore_old_data` invoked when the user chooses "Keep previous results".

---

### `em` (`bin/em.php`)

Elastic Manager — provisions and manages cloud compute instances (m3.2xl flavor) via SSH + Docker. Used when heavy computation needs to run on a remote host rather than locally.

---

## Global configuration (`bin/limits.php`)

```php
$max_frames                   = 5000;    // max DCD/MC frames
$max_frame_digits             = 7;       // zero-padded PDB filenames: -m0000001.pdb
$significant_digits_to_use    = 7;
$max_q_multiplier             = 1.01;
$batch_run_pr_size            = 50;
$update_mmc_extract_frequency = 10;
$update_iq_frequency          = 50;
$waxsis_convergence_mode      = 'normal';  // quick | normal | thorough
$waxsis_threads               = 64;
$waxsis_retries               = 2;
$waxsis_model_number          = 0;        // model 0 = Load Structure structure
```

Plot title constants live in `bin/titles.php`.

---

## Module / workflow overview

| Module JSON | PHP script | Purpose |
|---|---|---|
| `defineproject.json` | `defineproject.php` | Create/name project, set description |
| `loadsaxs.json` | `loadsaxs.php` | Upload experimental SAXS I(q) data |
| `loadstructure.json` | `loadstructure.php` | Upload PDB, run US-SOMO + WAXSiS model 0 |
| `loaddcd.json` | `loaddcd.php` | Upload MD DCD trajectory |
| `computeiqpr.json` | `computeiqpr.php` | Compute I(q)/P(r) for all frames; NNLS fits |
| `structureflex.json` | `structureflex.php` | Identify flexible regions |
| `runmmc.json` | `runmmc.php` | Run Monte Carlo sampling |
| `retrievemmc.json` | `retrievemmc.php` | Extract/process MC results |
| `finalmodel.json` | `finalmodel.php` | WAXSiS on preselected frames; final NNLS |
| `joinresults.json` | `joinresults.php` | Aggregate results across multiple projects |

Several modules have a companion `*_load.php` (e.g., `loadstructure_load.php`) for the data-loading pre-pass, and `*_funcs.php` / `*_defines.php` for extracted helper functions.

---

## Trace naming conventions

Trace names are used as both SAS data store keys and Plotly legend labels. HTML tags are valid in Plotly and intentional.

| Curve | Name |
|---|---|
| Experimental I(q) | `"Exp. I(q)"` |
| WAXSiS model 0 (Load Structure) | `"I(q)<sub>W</sub> mod. 0"` |
| WAXSiS frame N | `"I(q)<sub>W</sub> mod. N"` |
| NNLS fit | `"I(q)<sub>W</sub> NNLS fit"` |
| NNLS fit residuals | `"I(q)<sub>W</sub> fit Res./SD"` |
| CRYSOL / other I(q) | `"I(q) <label>"` |
| Experimental P(r) | `"Exp. P(r)"` |
| P(r) reconstruction | `"Recon."` |

**Frame number extraction invariant** — all downstream code extracts frame numbers as:
```php
intval( end( explode( ' ', $name ) ) )
```
The **last space-separated token** of every curve name must be the integer frame number. This is used in `frame_no_from_data_name()`, `initial_model_set()`, `plotlyhist.php`, and joinresults.

**CSV column headers must be plain text** (no HTML). Before calling `save_data_csv`, rename `"I(q)<sub>W</sub> ..."` data entries to their plain-text equivalents (`"I(q) WAXSiS ..."` for frame curves, `"I(q) NNLS fit"` for the fit) so the strip regex `/I\(q\) /` still works. These renames happen after all `add_plot` calls (which bake trace names) so they don't affect Plotly output.

---

## WAXSiS details

WAXSiS runs inside a Docker container via SSH:
```
ssh host docker run -i --rm -v <hostpath>/<rundir>:/genapp/run <image> waxsis -s <pdb> ...
```

Per-model cache files: `<procdir>/<basename>-waxsis_n.dat` / `_t.dat` / `_q.dat`  
(suffix `_n`=normal, `_t`=thorough, `_q`=quick)

Model 0 (Load Structure) cached as: `waxsis/intensity_waxsis<suffix>.calc`

`$cgstate->state->waxsis_load_convergence` records which convergence mode was used at Load Structure time; `finalmodel.php` compares against the current mode and recomputes model 0 if it changed.

---

## File naming conventions

PDB frames are zero-padded to `$max_frame_digits` (7) digits:
```
basename-m0000001.pdb   # frame 1
basename-m0000042.pdb   # frame 42
```

Helper functions in `common.php`:
- `model_no_from_pdb_name($pdb)` — extracts integer from filename
- `padded_model_no_from_pdb_name($pdb)` — returns zero-padded string
- `frame_no_from_data_name($name)` — extracts integer from trace name (last token)
- `extract_dcd_frame($frame, $pdb, $dcd, $outdir)` — calls `mdconvert`

---

## External tool wrappers

- **`waxsis.php`** — functions + defaults object for running WAXSiS via SSH+Docker. **Not a class** (noted as "should be converted to a CLASS!" in source). Key defaults: `maxq=0.5`, `qpoints=501`, `solvent_e_density=0.334`.
- **`crysol.php`** — wrapper for CRYSOL 2.8.4 and 3.2.1 (both available); defaults include ATSAS install paths.
- **`em.php`** — `em` class for elastic compute provisioning.
- **`calcs/`** — Perl scripts for structural calculations (`structcalcs.pl`, `calcpr.pl`, `pdbinfo.pl`, `splitmodels.pl`, etc.) called via `run_cmd()`.

---

## Common utility functions (`common.php`)

```php
run_cmd( $cmd, $exit_if_error=true, $array_result=false )
    // exec() wrapper; captures stdout+stderr; calls error_exit on non-zero exit

run_streaming_cmd( $cmd, $cb_on_write, ... )
    // proc_open wrapper; calls $cb_on_write($line) for each stdout line

error_exit( $msg, $nonotify=true, $cb=null, $icon='toast.png' )
    // echo JSON error message and exit; optionally calls $cb first

error_exit_admin( $msg )
    // same but appends "contact administrators" message

progress_text( $msg )
    // sends blue <h5> progress banner to UI via $ga->tcpmessage

json_exit()
    // echo json_encode($output) and exit

mkdir_if_needed( $dir )
object_set_defaults( $inobj, $defobj )
clean_up_filename_and_copy_if_needed( $filename )
nnls_results_to_html( $obj )     // formats NNLS weight table as HTML
confidence_legend()              // returns AlphaFold pLDDT color legend HTML
```

---

## Module JSON structure

Each `modules/*.json` defines a GenApp module:
```json
{
    "moduleid"   : "finalmodel",
    "label"      : "Final model selection using WAXSiS",
    "executable" : "finalmodel.php",
    "loadfields" : "finalmodel_load.php",
    "notify"     : "email",
    "panels"     : [ ... ],
    "fields"     : [ ... ]
}
```

Fields map HTML form inputs → `$input->fieldname` in PHP. Panels define the layout grid.

---

## Active branches

| Branch | Purpose |
|---|---|
| `dev` | Main development branch; PRs target this |
| `main` | Stable/release |
| `feature/iqw-trace-rename` | Rename WAXSiS traces to I(q)_W subscript notation |
| `fix/waxsis-stale-trace` | Fix stale "WAXSiS" trace in iqplotwaxsis plot |

Work done in this session targets `feature/iqw-trace-rename`.

---

## Known subtleties

1. **`{};` at file top** — every PHP file starts with `{};` (empty block). Required by GenApp's parser; do not remove.

2. **SAS data vs plot trace names** — `rename_data()` only renames the data store key; it does NOT update `->name` on traces already added to a plot via `add_plot()`. When you need to rename a displayed trace, use `rename_curve()` or remove and re-add the trace.

3. **`save_data_csv` replacement** — the `$nameto` parameter is the full replacement string for `preg_replace`. It replaces only the matched portion, not the whole name.

4. **State migration** — `iq_waxsis_nnlsresults` keys are trace names. When trace names change, a migration block must update old-style keys before any code reads them. The lazy migration pattern in `finalmodel.php` does this at the top of each run.

5. **`question_prior_results()`** — defined in `remove.php`. Requires `$ga`, `$cgstate`, and `$input` to be set globally. The `$restore_old_data` callback should restore any pre-computed outputs to `$output` so the user sees them even if they choose "Keep previous results".

6. **`SAS(false)` for non-fatal errors** — construct with `new SAS(false)` when you want `error_exit()` calls to return `false` instead of `exit`. Used for optional operations (e.g., computing stats for model 0 display) that should not abort the run if they fail.

7. **WAXSiS model 0 is frame 0 / `$waxsis_model_number`** — the Load Structure PDB gets model number 0 (not 1). `plotlyhist.php` identifies it by the loose comparison `$model == 0` (PHP loose-types the last token `"0"` as integer 0).
