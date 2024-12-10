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
 * Filename: Shared/ProgressManager.php
 */

declare(strict_types=1);

namespace Mcp\Shared;

use Mcp\Types\ProgressToken;
use Mcp\Types\McpModel;
use InvalidArgumentException;

/**
 * Creates a progress context for tracking progress
 */
class ProgressManager {
    public static function createContext(RequestContext $ctx, ?float $total = null): ProgressContext {
        if ($ctx->getMeta() === null || $ctx->getMeta()->getProgressToken() === null) {
            throw new \InvalidArgumentException('No progress token provided');
        }

        return new ProgressContext(
            $ctx->getSession(),
            $ctx->getMeta()->getProgressToken(),
            $total
        );
    }
}