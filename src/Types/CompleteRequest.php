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
 * Filename: Types/CompleteRequest.php
 */

declare(strict_types=1);

namespace Mcp\Types;

/**
 * Request for completion options
 */
class CompleteRequest extends Request {
    public function __construct(
        public readonly array $argument,
        public readonly PromptReference|ResourceReference $ref,
    ) {
        parent::__construct('completion/complete');
    }

    public function validate(): void {
        parent::validate();
        if (!isset($this->argument['name']) || !isset($this->argument['value'])) {
            throw new \InvalidArgumentException('Completion argument must have name and value');
        }
        if (empty($this->argument['name']) || empty($this->argument['value'])) {
            throw new \InvalidArgumentException('Completion argument name and value cannot be empty');
        }
        $this->ref->validate();
    }
}