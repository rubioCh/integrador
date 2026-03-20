<?php

namespace App\Services;

use GuzzleHttp\BodySummarizer;

class LargeBodySummarizer extends BodySummarizer
{
    public function __construct()
    {
        parent::__construct(1024 * 1024);
    }
}
