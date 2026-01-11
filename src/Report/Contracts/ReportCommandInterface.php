<?php

namespace App\Report\Contracts;

use App\Entity\User;

interface ReportCommandInterface
{
    /**
     * @param array<string,mixed> $payload
     */
    public function run(User $user, array $payload): ReportFile;
}