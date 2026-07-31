<?php

namespace Core\Products\Enums;

/**
 * Known server-provider capability identifiers.
 * Custom modules may declare additional capability strings beyond this set.
 */
enum ProductModuleCapability: string
{
    case ServerCreate = 'server.create';
    case ServerSuspend = 'server.suspend';
    case ServerUnsuspend = 'server.unsuspend';
    case ServerTerminate = 'server.terminate';
    case ServerReinstall = 'server.reinstall';
    case ServerRestart = 'server.restart';
    case ServerConsole = 'server.console';
    case ServerPanel = 'server.panel';
    case NodeAllocate = 'node.allocate';
    case NodeSync = 'node.sync';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::ServerCreate => __('Create server'),
            self::ServerSuspend => __('Suspend server'),
            self::ServerUnsuspend => __('Unsuspend server'),
            self::ServerTerminate => __('Terminate server'),
            self::ServerReinstall => __('Reinstall server'),
            self::ServerRestart => __('Restart server'),
            self::ServerConsole => __('Web console'),
            self::ServerPanel => __('External panel'),
            self::NodeAllocate => __('Allocate node'),
            self::NodeSync => __('Sync node'),
        };
    }

    /**
     * Default capability set expected for a typical server-provider product.
     *
     * @return list<self>
     */
    public static function serverLifecycle(): array
    {
        return [
            self::ServerCreate,
            self::ServerSuspend,
            self::ServerUnsuspend,
            self::ServerTerminate,
        ];
    }
}
