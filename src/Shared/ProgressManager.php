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
 * Progress information model
 */
class Progress implements McpModel {
    public function __construct(
        public readonly float $progress,
        public readonly ?float $total = null,
    ) {}

    public function validate(): void {
        if ($this->total !== null && $this->total < $this->progress) {
            throw new \InvalidArgumentException('Total cannot be less than progress');
        }
    }

    public function jsonSerialize(): mixed {
        return [
            'progress' => $this->progress,
            'total' => $this->total,
        ];
    }
}

/**
 * Progress tracking context
 */
class ProgressContext {
    private float $current = 0.0;

    public function __construct(
        private readonly BaseSession $session,
        private readonly ProgressToken $progressToken,
        private readonly ?float $total = null,
    ) {}

    public function progress(float $amount): void {
        $this->current += $amount;
        $this->session->sendProgressNotification(
            $this->progressToken,
            $this->current,
            $this->total
        );
    }

    public function getCurrent(): float {
        return $this->current;
    }

    public function getTotal(): ?float {
        return $this->total;
    }
}

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