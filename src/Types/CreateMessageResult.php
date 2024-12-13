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
 * content: TextContent | ImageContent
 */
class CreateMessageResult extends Result {
    public function __construct(
        public readonly TextContent|ImageContent $content,
        public readonly string $model,
        public readonly Role $role,
        public ?string $stopReason = null,
        ?Meta $_meta = null,
    ) {
        parent::__construct($_meta);
    }

    public function validate(): void {
        parent::validate();
        $this->content->validate();
        if (empty($this->model)) {
            throw new \InvalidArgumentException('Model name cannot be empty');
        }
        // role is an enum, no validation needed unless empty check wanted
    }
}