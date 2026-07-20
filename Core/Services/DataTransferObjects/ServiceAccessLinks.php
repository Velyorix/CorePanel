<?php

namespace Core\Services\DataTransferObjects;

final readonly class ServiceAccessLinks
{
    public function __construct(
        public ?string $panelUrl,
        public ?string $consoleUrl,
        public bool $canOpenPanel,
        public bool $canOpenConsole,
    ) {
    }

    public function hasPanel(): bool
    {
        return $this->panelUrl !== null && $this->panelUrl !== '';
    }

    public function hasConsole(): bool
    {
        return $this->consoleUrl !== null && $this->consoleUrl !== '';
    }

    /**
     * @return array{panel_url: ?string, console_url: ?string, can_open_panel: bool, can_open_console: bool}
     */
    public function toArray(): array
    {
        return [
            'panel_url' => $this->panelUrl,
            'console_url' => $this->consoleUrl,
            'can_open_panel' => $this->canOpenPanel,
            'can_open_console' => $this->canOpenConsole,
        ];
    }
}
