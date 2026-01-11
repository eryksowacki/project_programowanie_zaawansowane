<?php

namespace App\Report\Contracts;

final readonly class ReportFile
{
    public function __construct(
        public string $filename,
        public string $contentType,
        public string $content
    ) {}
}