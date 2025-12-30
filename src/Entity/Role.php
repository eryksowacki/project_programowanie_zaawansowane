<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'roles')]
class Role
{
#[ORM\Id]
#[ORM\GeneratedValue]
#[ORM\Column]
private ?int $id = null;

// np. ROLE_EMPLOYEE, ROLE_MANAGER
#[ORM\Column(length: 50, unique: true)]
private string $code;

// opcjonalnie: ładna nazwa do UI
#[ORM\Column(length: 100)]
private string $name;

public function getId(): ?int { return $this->id; }
public function getCode(): string { return $this->code; }
public function setCode(string $code): self { $this->code = $code; return $this; }

public function getName(): string { return $this->name; }
public function setName(string $name): self { $this->name = $name; return $this; }
}
