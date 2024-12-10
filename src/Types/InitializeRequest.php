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
 * Filename: Types/InitializeRequest.php
 */

declare(strict_types=1);

namespace Mcp\Types;

/**
 * Initialize request from client to server
 */
class InitializeRequest extends Request {
    public function __construct(
        public readonly ClientCapabilities $capabilities,
        public readonly Implementation $clientInfo,
        public readonly string $protocolVersion,
    ) {
        parent::__construct('initialize');
    }

    public function validate(): void {
        parent::validate();
        $this->capabilities->validate();
        $this->clientInfo->validate();
        if (empty($this->protocolVersion)) {
            throw new \InvalidArgumentException('Protocol version cannot be empty');
        }
    }
}