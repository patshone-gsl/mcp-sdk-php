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
 * Filename: Types/ServerNotification.php
 */

declare(strict_types=1);

namespace Mcp\Types;

/**
 * Union type for server notifications:
 * type ServerNotification =
 *   | CancelledNotification
 *   | ProgressNotification
 *   | ResourceListChangedNotification
 *   | ResourceUpdatedNotification
 *   | PromptListChangedNotification
 *   | ToolListChangedNotification
 *   | LoggingMessageNotification
 *
 * Represented as a root model holding one valid Notification variant.
 */
class ServerNotification implements McpModel {
    use ExtraFieldsTrait;

    private Notification $notification;

    public function __construct(Notification $notification) {
        if (!(
            $notification instanceof CancelledNotification ||
            $notification instanceof ProgressNotification ||
            $notification instanceof ResourceListChangedNotification ||
            $notification instanceof ResourceUpdatedNotification ||
            $notification instanceof PromptListChangedNotification ||
            $notification instanceof ToolListChangedNotification ||
            $notification instanceof LoggingMessageNotification
        )) {
            throw new \InvalidArgumentException('Invalid server notification type');
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