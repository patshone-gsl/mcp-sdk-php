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
 * Filename: Types/CreateMessageResult.php
 */

declare(strict_types=1);

namespace Mcp\Types;

/**
 * Result of a create message request
 */
class CreateMessageResult extends Result {
    public function __construct(
        public readonly Content $content,
        public readonly string $model,
        public readonly Role $role,
        public ?string $stopReason = null,
        ?array $meta = null,
    ) {
        parent::__construct($meta);
    }

    public function validate(): void {
        parent::validate();
        if (!($this->content instanceof TextContent || $this->content instanceof ImageContent)) {
            throw new \InvalidArgumentException('Message content must be instance of TextContent or ImageContent');
        }
        $this->content->validate();
        if (empty($this->model)) {
            throw new \InvalidArgumentException('Model name cannot be empty');
        }
    }
}