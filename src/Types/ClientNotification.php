<?php

/**
 * Model Context Protocol SDK for PHP
 *
 * (c) 2024 Logiscape LLC <https://logiscape.com>
 *
 * Based on the Python SDK for the Model Context Protocol
 * https://github.com/modelcontextprotocol/python-sdk
 *
 * PHP conversion developed by:
 * - Josh Abbott
 * - Claude 3.5 Sonnet (Anthropic AI model)
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @package    logiscape/mcp-sdk-php
 * @author     Josh Abbott <https://joshabbott.com>
 * @copyright  Logiscape LLC
 * @license    MIT License
 * @link       https://github.com/logiscape/mcp-sdk-php
 *
 * Filename: Types/ClientNotification.php
 */

declare(strict_types=1);

namespace Mcp\Types;

/**
 * Union type for client notifications:
 * type ClientNotification =
 *   | CancelledNotification
 *   | InitializedNotification
 *   | ProgressNotification
 *   | RootsListChangedNotification
 *
 * This acts as a root model for that union.
 */
class ClientNotification implements McpModel {
    use ExtraFieldsTrait;

    private Notification $notification;

    public function __construct(
        Notification $notification
    ) {
        if (!(
            $notification instanceof CancelledNotification ||
            $notification instanceof InitializedNotification ||
            $notification instanceof ProgressNotification ||
            $notification instanceof RootsListChangedNotification
        )) {
            throw new \InvalidArgumentException('Invalid client notification type');
        }
        $this->notification = $notification;
    }

    public function validate(): void {
        $this->notification->validate();
    }

    public function getNotification(): Notification {
        return $this->notification;
    }

    public function jsonSerialize(): mixed {
        $data = $this->notification->jsonSerialize();
        return array_merge((array)$data, $this->extraFields);
    }
}