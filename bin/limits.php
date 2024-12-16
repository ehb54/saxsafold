<?php

{};

## limit # of frames for computations
$max_frames                             = 5000;

### $max_frame_digits should not be changed unless we are going to support 10M models or more
$max_frame_digits                       = 7; 

## significant digits to keep
$significant_digits_to_use              = 5;

## extend max q for extrapolation issues
$max_q_multiplier                       = 1.01;

## update extract mmc frequency
$update_mmc_extract_frequency           = 10;

## batch run p(r)
$batch_run_pr_size                      = 50;

## update i(q) frequency
$update_iq_frequency                    = 50;

## testing limits
$test_limit_max_computeiqpr_frames      = 0;

## global waxsis configs

## waxsis mode quick, normal, thourough
# $waxsis_converegence_mode               = 'normal';
$waxsis_convergence_mode                = 'quick';

$waxsis_threads                         = 10;
$waxsis_retries                         = 1;
$waxsis_model_number                    = 0;
