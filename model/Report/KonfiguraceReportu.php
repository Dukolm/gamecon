<?php

declare(strict_types=1);

namespace Gamecon\Report;

class KonfiguraceReportu
{
    public const NO_ROW_TO_FREEZE = 0;

    private $headerFontSize = 10;
    private $bodyFontSize   = 10;
    private $rowToFreeze    = 1;
    /** @var null|int */
    private $maxGenericColumnWidth = null;
    /** @var int[] */
    private         $columnsWidths   = [];
    private ?string $destinationFile = null;

    public function getHeaderFontSize(): int
    {
        return $this->headerFontSize;
    }

    public function setHeaderFontSize(int $headerFontSize): self
    {
        $this->headerFontSize = $headerFontSize;
        return $this;
    }

    public function getBodyFontSize(): int
    {
        return $this->bodyFontSize;
    }

    public function setBodyFontSize(int $bodyFontSize): self
    {
        $this->bodyFontSize = $bodyFontSize;
        return $this;
    }

    public function getRowToFreeze(): int
    {
        return $this->rowToFreeze;
    }

    public function setRowToFreeze(int $rowToFreeze): self
    {
        $this->rowToFreeze = $rowToFreeze;
        return $this;
    }

    public function getMaxGenericColumnWidth(): ?int
    {
        return $this->maxGenericColumnWidth;
    }

    public function setMaxGenericColumnWidth(?int $maxGenericColumnWidth): self
    {
        $this->maxGenericColumnWidth = $maxGenericColumnWidth;
        return $this;
    }

    /**
     * @return int[]
     */
    public function getColumnsWidths(): array
    {
        return $this->columnsWidths;
    }

    /**
     * @param int[] $columnsWidths
     */
    public function setColumnsWidths(array $columnsWidths): self
    {
        $this->columnsWidths = $columnsWidths;
        return $this;
    }

    public function setDestinationFile(string $destinationFile)
    {
        $this->destinationFile = $destinationFile;
    }

    public function getDestinationFile(): ?string
    {
        return $this->destinationFile;
    }

}
