<?php
namespace App\Event\Radio;

use App\Entity\Station;
use Symfony\Contracts\EventDispatcher\Event;

class WriteLiquidsoapConfiguration extends Event
{
    protected array $configLines;

    protected Station $station;

    protected bool $forEditing;

    public function __construct(Station $station, bool $forEditing = false)
    {
        $this->station = $station;
        $this->configLines = [];
        $this->forEditing = $forEditing;
    }

    public function getStation(): Station
    {
        return $this->station;
    }

    public function isForEditing(): bool
    {
        return $this->forEditing;
    }

    /**
     * Append one of more lines to the end of the configuration string.
     *
     * @param array $lines
     */
    public function appendLines(array $lines): void
    {
        $this->configLines = array_merge($this->configLines, [''], $lines);
    }

    /**
     * Prepend one or more lines to the front of the configuration string.
     *
     * @param array $lines
     */
    public function prependLines(array $lines): void
    {
        $this->configLines = array_merge($lines, [''], $this->configLines);
    }

    /**
     * Compile the configuration lines together and return the result.
     *
     * @return string
     */
    public function buildConfiguration(): string
    {
        return implode("\n", $this->configLines);
    }
}
