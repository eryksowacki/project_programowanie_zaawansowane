<?php

namespace App\Report\Contracts;

use App\Entity\Document;

interface KpirHtmlBuilderInterface
{
    /** @return $this */
    public function withCompany(string $name, string $address, string $taxId): self;

    /** @return $this */
    public function withPeriodTitle(string $periodTitle): self;

    /**
     * @param Document[] $docs
     * @return $this
     */
    public function withDocs(array $docs): self;

    public function build(): string;
}