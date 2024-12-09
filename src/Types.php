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
 * Filename: Types.php
 */

declare(strict_types=1);

namespace Mcp\Types;

use JsonSerializable;
use InvalidArgumentException;
use RuntimeException;

/**
 * Base trait for all MCP models to allow extra fields
 */
trait ExtraFieldsTrait {
    private array $extraFields = [];

    public function __set(string $name, mixed $value): void {
        $this->extraFields[$name] = $value;
    }

    public function __get(string $name): mixed {
        return $this->extraFields[$name] ?? null;
    }

    public function __isset(string $name): bool {
        return isset($this->extraFields[$name]);
    }
}

/**
 * Base interface for all MCP models
 */
interface McpModel extends JsonSerializable {
    public function validate(): void;
}

/**
 * Role enum representing possible roles in the conversation
 */
enum Role: string {
    case ASSISTANT = 'assistant';
    case USER = 'user';
}

/**
 * LoggingLevel enum representing possible logging levels
 */
enum LoggingLevel: string {
    case EMERGENCY = 'emergency';
    case ALERT = 'alert';
    case CRITICAL = 'critical';
    case ERROR = 'error';
    case WARNING = 'warning';
    case NOTICE = 'notice';
    case INFO = 'info';
    case DEBUG = 'debug';
}

/**
 * Base class for content types
 */
abstract class Content implements McpModel {
    use ExtraFieldsTrait;

    public function __construct(
        public readonly string $type,
        public ?array $annotations = null,
    ) {}

    public function jsonSerialize(): mixed {
        $data = get_object_vars($this);
        return array_merge($data, $this->extraFields);
    }
}

/**
 * Text content provided to or from an LLM
 */
class TextContent extends Content {
    public function __construct(
        public readonly string $text,
        ?array $annotations = null,
    ) {
        parent::__construct('text', $annotations);
    }

    public function validate(): void {
        if (empty($this->text)) {
            throw new \InvalidArgumentException('Text content cannot be empty');
        }
    }
}

/**
 * Image content provided to or from an LLM
 */
class ImageContent extends Content {
    public function __construct(
        public readonly string $data,
        public readonly string $mimeType,
        ?array $annotations = null,
    ) {
        parent::__construct('image', $annotations);
    }

    public function validate(): void {
        if (empty($this->data)) {
            throw new \InvalidArgumentException('Image data cannot be empty');
        }
        if (empty($this->mimeType)) {
            throw new \InvalidArgumentException('MIME type cannot be empty');
        }
    }
}

/**
 * Base class for resource contents
 */
abstract class ResourceContents implements McpModel {
    use ExtraFieldsTrait;

    public function __construct(
        public readonly string $uri,
        public ?string $mimeType = null,
    ) {}

    public function jsonSerialize(): mixed {
        $data = get_object_vars($this);
        return array_merge($data, $this->extraFields);
    }
}

/**
 * Text resource contents
 */
class TextResourceContents extends ResourceContents {
    public function __construct(
        public readonly string $text,
        string $uri,
        ?string $mimeType = null,
    ) {
        parent::__construct($uri, $mimeType);
    }

    public function validate(): void {
        if (empty($this->uri)) {
            throw new \InvalidArgumentException('Resource URI cannot be empty');
        }
        if (empty($this->text)) {
            throw new \InvalidArgumentException('Resource text cannot be empty');
        }
    }
}

/**
 * Binary resource contents
 */
class BlobResourceContents extends ResourceContents {
    public function __construct(
        public readonly string $blob,
        string $uri,
        ?string $mimeType = null,
    ) {
        parent::__construct($uri, $mimeType);
    }

    public function validate(): void {
        if (empty($this->uri)) {
            throw new \InvalidArgumentException('Resource URI cannot be empty');
        }
        if (empty($this->blob)) {
            throw new \InvalidArgumentException('Resource blob cannot be empty');
        }
    }
}

/**
 * Embedded resource in a prompt or tool call result
 */
class EmbeddedResource implements McpModel {
    use ExtraFieldsTrait;

    public function __construct(
        public readonly ResourceContents $resource,
        public readonly string $type = 'resource',
        public ?array $annotations = null,
    ) {}

    public function validate(): void {
        $this->resource->validate();
        if ($this->type !== 'resource') {
            throw new \InvalidArgumentException('Embedded resource type must be "resource"');
        }
    }

    public function jsonSerialize(): mixed {
        $data = get_object_vars($this);
        return array_merge($data, $this->extraFields);
    }
}

/**
 * A known resource that the server is capable of reading
 */
class Resource implements McpModel {
    use ExtraFieldsTrait;

    public function __construct(
        public readonly string $name,
        public readonly string $uri,
        public ?string $description = null,
        public ?string $mimeType = null,
        public ?array $annotations = null,
    ) {}

    public function validate(): void {
        if (empty($this->name)) {
            throw new \InvalidArgumentException('Resource name cannot be empty');
        }
        if (empty($this->uri)) {
            throw new \InvalidArgumentException('Resource URI cannot be empty');
        }
    }

    public function jsonSerialize(): mixed {
        $data = get_object_vars($this);
        return array_merge($data, $this->extraFields);
    }
}

/**
 * A template description for resources available on the server
 */
class ResourceTemplate implements McpModel {
    use ExtraFieldsTrait;

    public function __construct(
        public readonly string $name,
        public readonly string $uriTemplate,
        public ?string $description = null,
        public ?string $mimeType = null,
        public ?array $annotations = null,
    ) {}

    public function validate(): void {
        if (empty($this->name)) {
            throw new \InvalidArgumentException('Resource template name cannot be empty');
        }
        if (empty($this->uriTemplate)) {
            throw new \InvalidArgumentException('Resource template URI template cannot be empty');
        }
    }

    public function jsonSerialize(): mixed {
        $data = get_object_vars($this);
        return array_merge($data, $this->extraFields);
    }
}

/**
 * A reference to a resource or resource template definition
 */
class ResourceReference implements McpModel {
    use ExtraFieldsTrait;

    public function __construct(
        public readonly string $uri,
        public readonly string $type = 'ref/resource',
    ) {}

    public function validate(): void {
        if (empty($this->uri)) {
            throw new \InvalidArgumentException('Resource reference URI cannot be empty');
        }
        if ($this->type !== 'ref/resource') {
            throw new \InvalidArgumentException('Resource reference type must be "ref/resource"');
        }
    }

    public function jsonSerialize(): mixed {
        $data = get_object_vars($this);
        return array_merge($data, $this->extraFields);
    }
}

/**
 * Describes a message issued to or received from an LLM API
 */
class SamplingMessage implements McpModel {
    use ExtraFieldsTrait;

    public function __construct(
        public readonly Content $content,
        public readonly Role $role,
    ) {}

    public function validate(): void {
        $this->content->validate();
        if (!($this->content instanceof TextContent || $this->content instanceof ImageContent)) {
            throw new \InvalidArgumentException('Sampling message content must be text or image');
        }
    }

    public function jsonSerialize(): mixed {
        $data = get_object_vars($this);
        return array_merge($data, $this->extraFields);
    }
}

/**
 * Describes a message returned as part of a prompt
 */
class PromptMessage implements McpModel {
    use ExtraFieldsTrait;

    public function __construct(
        public readonly Content|EmbeddedResource $content,
        public readonly Role $role,
    ) {}

    public function validate(): void {
        $this->content->validate();
    }

    public function jsonSerialize(): mixed {
        $data = get_object_vars($this);
        return array_merge($data, $this->extraFields);
    }
}

/**
 * Describes an argument that a prompt can accept
 */
class PromptArgument implements McpModel {
    use ExtraFieldsTrait;

    public function __construct(
        public readonly string $name,
        public ?string $description = null,
        public bool $required = false,
    ) {}

    public function validate(): void {
        if (empty($this->name)) {
            throw new \InvalidArgumentException('Prompt argument name cannot be empty');
        }
    }

    public function jsonSerialize(): mixed {
        $data = get_object_vars($this);
        return array_merge($data, $this->extraFields);
    }
}

/**
 * A prompt or prompt template that the server offers
 */
class Prompt implements McpModel {
    use ExtraFieldsTrait;

    /**
     * @param PromptArgument[] $arguments
     */
    public function __construct(
        public readonly string $name,
        public ?string $description = null,
        public array $arguments = [],
    ) {}

    public function validate(): void {
        if (empty($this->name)) {
            throw new \InvalidArgumentException('Prompt name cannot be empty');
        }
        foreach ($this->arguments as $argument) {
            if (!$argument instanceof PromptArgument) {
                throw new \InvalidArgumentException('Prompt arguments must be instances of PromptArgument');
            }
            $argument->validate();
        }
    }

    public function jsonSerialize(): mixed {
        $data = get_object_vars($this);
        return array_merge($data, $this->extraFields);
    }
}

/**
 * Identifies a prompt
 */
class PromptReference implements McpModel {
    use ExtraFieldsTrait;

    public function __construct(
        public readonly string $name,
        public readonly string $type = 'ref/prompt',
    ) {}

    public function validate(): void {
        if (empty($this->name)) {
            throw new \InvalidArgumentException('Prompt reference name cannot be empty');
        }
        if ($this->type !== 'ref/prompt') {
            throw new \InvalidArgumentException('Prompt reference type must be "ref/prompt"');
        }
    }

    public function jsonSerialize(): mixed {
        $data = get_object_vars($this);
        return array_merge($data, $this->extraFields);
    }
}

/**
 * Represents a root directory or file that the server can operate on
 */
class Root implements McpModel {
    use ExtraFieldsTrait;

    public function __construct(
        public readonly string $uri,
        public ?string $name = null,
    ) {}

    public function validate(): void {
        if (empty($this->uri)) {
            throw new \InvalidArgumentException('Root URI cannot be empty');
        }
        if (!str_starts_with($this->uri, 'file://')) {
            throw new \InvalidArgumentException('Root URI must start with file://');
        }
    }

    public function jsonSerialize(): mixed {
        $data = get_object_vars($this);
        return array_merge($data, $this->extraFields);
    }
}

/**
 * Describes the name and version of an MCP implementation
 */
class Implementation implements McpModel {
    use ExtraFieldsTrait;

    public function __construct(
        public readonly string $name,
        public readonly string $version,
    ) {}

    public function validate(): void {
        if (empty($this->name)) {
            throw new \InvalidArgumentException('Implementation name cannot be empty');
        }
        if (empty($this->version)) {
            throw new \InvalidArgumentException('Implementation version cannot be empty');
        }
    }

    public function jsonSerialize(): mixed {
        $data = get_object_vars($this);
        return array_merge($data, $this->extraFields);
    }
}

/**
 * Hints to use for model selection
 */
class ModelHint implements McpModel {
    use ExtraFieldsTrait;

    public function __construct(
        public ?string $name = null,
    ) {}

    public function validate(): void {
        // No validation needed as all fields are optional
    }

    public function jsonSerialize(): mixed {
        $data = get_object_vars($this);
        return array_merge($data, $this->extraFields);
    }
}

/**
 * The server's preferences for model selection
 */
class ModelPreferences implements McpModel {
    use ExtraFieldsTrait;

    /**
     * @param ModelHint[] $hints
     */
    public function __construct(
        public ?float $costPriority = null,
        public ?float $speedPriority = null,
        public ?float $intelligencePriority = null,
        public array $hints = [],
    ) {}

    public function validate(): void {
        foreach ([$this->costPriority, $this->speedPriority, $this->intelligencePriority] as $priority) {
            if ($priority !== null && ($priority < 0 || $priority > 1)) {
                throw new \InvalidArgumentException('Priority values must be between 0 and 1');
            }
        }
        foreach ($this->hints as $hint) {
            if (!$hint instanceof ModelHint) {
                throw new \InvalidArgumentException('Hints must be instances of ModelHint');
            }
            $hint->validate();
        }
    }

    public function jsonSerialize(): mixed {
        $data = get_object_vars($this);
        return array_merge($data, $this->extraFields);
    }
}

/**
 * Base class for capabilities
 */
abstract class Capabilities implements McpModel {
    use ExtraFieldsTrait;

    public function __construct(
        public ?array $experimental = null,
    ) {}

    public function validate(): void {
        // No validation needed for base capabilities
    }

    public function jsonSerialize(): mixed {
        $data = get_object_vars($this);
        return array_merge($data, $this->extraFields);
    }
}

/**
 * Structure for client roots capability
 */
class ClientRootsCapability implements McpModel {
    public function __construct(
        public readonly bool $listChanged,
    ) {}

    public function validate(): void {
        // No additional validation needed
    }

    public function jsonSerialize(): mixed {
        return ['listChanged' => $this->listChanged];
    }
}

/**
 * Client capabilities
 */
class ClientCapabilities extends Capabilities {
    public function __construct(
        public ?ClientRootsCapability $roots = null,
        public ?array $sampling = null,  // Allows any additional properties
        ?array $experimental = null,
    ) {
        parent::__construct($experimental);
    }

    public function validate(): void {
        parent::validate();
        if ($this->roots !== null) {
            $this->roots->validate();
        }
    }
}

/**
 * Structure for server prompts capability
 */
class ServerPromptsCapability implements McpModel {
    public function __construct(
        public readonly bool $listChanged,
    ) {}

    public function validate(): void {
        // No additional validation needed
    }

    public function jsonSerialize(): mixed {
        return ['listChanged' => $this->listChanged];
    }
}

/**
 * Structure for server resources capability
 */
class ServerResourcesCapability implements McpModel {
    public function __construct(
        public readonly bool $listChanged,
        public readonly bool $subscribe,
    ) {}

    public function validate(): void {
        // No additional validation needed
    }

    public function jsonSerialize(): mixed {
        return [
            'listChanged' => $this->listChanged,
            'subscribe' => $this->subscribe,
        ];
    }
}

/**
 * Structure for server tools capability
 */
class ServerToolsCapability implements McpModel {
    public function __construct(
        public readonly bool $listChanged,
    ) {}

    public function validate(): void {
        // No additional validation needed
    }

    public function jsonSerialize(): mixed {
        return ['listChanged' => $this->listChanged];
    }
}

/**
 * Server capabilities
 */
class ServerCapabilities extends Capabilities {
    public function __construct(
        public ?array $logging = null,  // Allows any additional properties
        public ?ServerPromptsCapability $prompts = null,
        public ?ServerResourcesCapability $resources = null,
        public ?ServerToolsCapability $tools = null,
        ?array $experimental = null,
    ) {
        parent::__construct($experimental);
    }

    public function validate(): void {
        parent::validate();
        if ($this->prompts !== null) {
            $this->prompts->validate();
        }
        if ($this->resources !== null) {
            $this->resources->validate();
        }
        if ($this->tools !== null) {
            $this->tools->validate();
        }
    }
}

/**
 * Base type for all request IDs
 */
class RequestId implements McpModel {
    public function __construct(
        private string|int $id,
    ) {}

    public function getValue(): string|int {
        return $this->id;
    }

    public function validate(): void {
        if (is_string($this->id) && empty($this->id)) {
            throw new \InvalidArgumentException('Request ID string cannot be empty');
        }
    }

    public function jsonSerialize(): mixed {
        return $this->id;
    }
}

/**
 * Progress token for tracking long-running operations
 */
class ProgressToken implements McpModel {
    public function __construct(
        private string|int $token,
    ) {}

    public function getValue(): string|int {
        return $this->token;
    }

    public function validate(): void {
        if (is_string($this->token) && empty($this->token)) {
            throw new \InvalidArgumentException('Progress token string cannot be empty');
        }
    }

    public function jsonSerialize(): mixed {
        return $this->token;
    }
}

/**
 * Base class for all requests
 */
abstract class Request implements McpModel {
    use ExtraFieldsTrait;

    public function __construct(
        public readonly string $method,
        public ?array $params = null,
    ) {}

    public function validate(): void {
        if (empty($this->method)) {
            throw new \InvalidArgumentException('Request method cannot be empty');
        }
    }

    public function jsonSerialize(): mixed {
        $data = get_object_vars($this);
        return array_merge($data, $this->extraFields);
    }
}

/**
 * Base class for all responses
 */
abstract class Result implements McpModel {
    use ExtraFieldsTrait;

    public function __construct(
        public ?array $meta = null,
    ) {}

    public function validate(): void {
        // Base result validation - can be extended by child classes
    }

    public function jsonSerialize(): mixed {
        $data = get_object_vars($this);
        return array_merge($data, $this->extraFields);
    }
}

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

/**
 * Server's response to initialization request
 */
class InitializeResult extends Result {
    public function __construct(
        public readonly ServerCapabilities $capabilities,
        public readonly Implementation $serverInfo,
        public readonly string $protocolVersion,
        public ?string $instructions = null,
        ?array $meta = null,
    ) {
        parent::__construct($meta);
    }

    public function validate(): void {
        parent::validate();
        $this->capabilities->validate();
        $this->serverInfo->validate();
        if (empty($this->protocolVersion)) {
            throw new \InvalidArgumentException('Protocol version cannot be empty');
        }
    }
}

/**
 * Base class for all paginated requests
 */
abstract class PaginatedRequest extends Request {
    public function __construct(
        string $method,
        public ?string $cursor = null,
    ) {
        parent::__construct($method);
    }
}

/**
 * Base class for all paginated results
 */
abstract class PaginatedResult extends Result {
    public function __construct(
        public ?string $nextCursor = null,
        ?array $meta = null,
    ) {
        parent::__construct($meta);
    }
}

/**
 * JSON-RPC message envelope
 */
class JsonRpcMessage implements McpModel {
    public function __construct(
        public readonly string $jsonrpc = '2.0',
        public ?RequestId $id = null,
        public ?string $method = null,
        public ?array $params = null,
        public ?Result $result = null,
        public ?array $error = null,
    ) {}

    public function validate(): void {
        if ($this->jsonrpc !== '2.0') {
            throw new \InvalidArgumentException('JSON-RPC version must be "2.0"');
        }

        if ($this->error !== null) {
            if (!isset($this->error['code']) || !isset($this->error['message'])) {
                throw new \InvalidArgumentException('JSON-RPC error must have code and message');
            }
        }

        if ($this->id !== null) {
            $this->id->validate();
        }

        if ($this->result !== null) {
            $this->result->validate();
        }
    }

    public function jsonSerialize(): mixed {
        $data = [
            'jsonrpc' => $this->jsonrpc,
        ];

        if ($this->id !== null) {
            $data['id'] = $this->id;
        }
        if ($this->method !== null) {
            $data['method'] = $this->method;
        }
        if ($this->params !== null) {
            $data['params'] = $this->params;
        }
        if ($this->result !== null) {
            $data['result'] = $this->result;
        }
        if ($this->error !== null) {
            $data['error'] = $this->error;
        }

        return $data;
    }
}

/**
 * Definition for a tool the client can call
 */
class Tool implements McpModel {
    use ExtraFieldsTrait;

    public function __construct(
        public readonly string $name,
        public readonly array $inputSchema,
        public ?string $description = null,
    ) {}

    public function validate(): void {
        if (empty($this->name)) {
            throw new \InvalidArgumentException('Tool name cannot be empty');
        }
        if (!isset($this->inputSchema['type']) || $this->inputSchema['type'] !== 'object') {
            throw new \InvalidArgumentException('Tool input schema must be an object type');
        }
    }

    public function jsonSerialize(): mixed {
        $data = get_object_vars($this);
        return array_merge($data, $this->extraFields);
    }
}

/**
 * Base class for all notifications
 */
abstract class Notification implements McpModel {
    use ExtraFieldsTrait;

    public function __construct(
        public readonly string $method,
        public ?array $params = null,
    ) {}

    public function validate(): void {
        if (empty($this->method)) {
            throw new \InvalidArgumentException('Notification method cannot be empty');
        }
    }

    public function jsonSerialize(): mixed {
        $data = get_object_vars($this);
        return array_merge($data, $this->extraFields);
    }
}

/**
 * Notification for cancelled requests
 */
class CancelledNotification extends Notification {
    public function __construct(
        public readonly RequestId $requestId,
        public ?string $reason = null,
    ) {
        parent::__construct('notifications/cancelled');
    }

    public function validate(): void {
        parent::validate();
        $this->requestId->validate();
    }
}

/**
 * Notification for initialization completion
 */
class InitializedNotification extends Notification {
    public function __construct() {
        parent::__construct('notifications/initialized');
    }
}

/**
 * Notification for progress updates
 */
class ProgressNotification extends Notification {
    public function __construct(
        public readonly ProgressToken $progressToken,
        public readonly float $progress,
        public ?float $total = null,
    ) {
        parent::__construct('notifications/progress');
    }

    public function validate(): void {
        parent::validate();
        $this->progressToken->validate();
        if ($this->total !== null && $this->total < $this->progress) {
            throw new \InvalidArgumentException('Total progress cannot be less than current progress');
        }
    }
}

/**
 * Notification for logging messages
 */
class LoggingMessageNotification extends Notification {
    public function __construct(
        public readonly mixed $data,
        public readonly LoggingLevel $level,
        public ?string $logger = null,
    ) {
        parent::__construct('notifications/message');
    }

    public function validate(): void {
        parent::validate();
        if ($this->data === null) {
            throw new \InvalidArgumentException('Logging message data cannot be null');
        }
    }
}

/**
 * Notification for resource list changes
 */
class ResourceListChangedNotification extends Notification {
    public function __construct() {
        parent::__construct('notifications/resources/list_changed');
    }
}

/**
 * Notification for resource updates
 */
class ResourceUpdatedNotification extends Notification {
    public function __construct(
        public readonly string $uri,
    ) {
        parent::__construct('notifications/resources/updated');
    }

    public function validate(): void {
        parent::validate();
        if (empty($this->uri)) {
            throw new \InvalidArgumentException('Resource URI cannot be empty');
        }
    }
}

/**
 * Notification for prompt list changes
 */
class PromptListChangedNotification extends Notification {
    public function __construct() {
        parent::__construct('notifications/prompts/list_changed');
    }
}

/**
 * Notification for tool list changes
 */
class ToolListChangedNotification extends Notification {
    public function __construct() {
        parent::__construct('notifications/tools/list_changed');
    }
}

/**
 * Notification for roots list changes
 */
class RootsListChangedNotification extends Notification {
    public function __construct() {
        parent::__construct('notifications/roots/list_changed');
    }
}

/**
 * Union type for client notifications
 */
class ClientNotification {
    private Notification $notification;

    public function __construct(
        Notification $notification
    ) {
        if (!($notification instanceof CancelledNotification ||
            $notification instanceof InitializedNotification ||
            $notification instanceof ProgressNotification ||
            $notification instanceof RootsListChangedNotification)) {
            throw new \InvalidArgumentException('Invalid client notification type');
        }
        $this->notification = $notification;
    }

    public function getNotification(): Notification {
        return $this->notification;
    }
}

/**
 * Union type for server notifications
 */
class ServerNotification {
    private Notification $notification;

    public function __construct(
        Notification $notification
    ) {
        if (!($notification instanceof CancelledNotification ||
            $notification instanceof ProgressNotification ||
            $notification instanceof ResourceListChangedNotification ||
            $notification instanceof ResourceUpdatedNotification ||
            $notification instanceof PromptListChangedNotification ||
            $notification instanceof ToolListChangedNotification ||
            $notification instanceof LoggingMessageNotification)) {
            throw new \InvalidArgumentException('Invalid server notification type');
        }
        $this->notification = $notification;
    }

    public function getNotification(): Notification {
        return $this->notification;
    }
}

/**
 * Ping request for connection checks
 */
class PingRequest extends Request {
    public function __construct() {
        parent::__construct('ping');
    }
}

/**
 * Request to list available resources
 */
class ListResourcesRequest extends PaginatedRequest {
    public function __construct(?string $cursor = null) {
        parent::__construct('resources/list', $cursor);
    }
}

/**
 * Result of listing available resources
 */
class ListResourcesResult extends PaginatedResult {
    /**
     * @param Resource[] $resources
     */
    public function __construct(
        public readonly array $resources,
        ?string $nextCursor = null,
        ?array $meta = null,
    ) {
        parent::__construct($nextCursor, $meta);
    }

    public function validate(): void {
        parent::validate();
        foreach ($this->resources as $resource) {
            if (!$resource instanceof Resource) {
                throw new \InvalidArgumentException('Resources must be instances of Resource');
            }
            $resource->validate();
        }
    }
}

/**
 * Request to read a specific resource
 */
class ReadResourceRequest extends Request {
    public function __construct(
        public readonly string $uri,
    ) {
        parent::__construct('resources/read');
    }

    public function validate(): void {
        parent::validate();
        if (empty($this->uri)) {
            throw new \InvalidArgumentException('Resource URI cannot be empty');
        }
    }
}

/**
 * Result of reading a resource
 */
class ReadResourceResult extends Result {
    /**
     * @param (TextResourceContents|BlobResourceContents)[] $contents
     */
    public function __construct(
        public readonly array $contents,
        ?array $meta = null,
    ) {
        parent::__construct($meta);
    }

    public function validate(): void {
        parent::validate();
        foreach ($this->contents as $content) {
            if (!($content instanceof TextResourceContents || $content instanceof BlobResourceContents)) {
                throw new \InvalidArgumentException('Resource contents must be instances of TextResourceContents or BlobResourceContents');
            }
            $content->validate();
        }
    }
}

/**
 * Request to subscribe to resource updates
 */
class SubscribeRequest extends Request {
    public function __construct(
        public readonly string $uri,
    ) {
        parent::__construct('resources/subscribe');
    }

    public function validate(): void {
        parent::validate();
        if (empty($this->uri)) {
            throw new \InvalidArgumentException('Resource URI cannot be empty');
        }
    }
}

/**
 * Request to unsubscribe from resource updates
 */
class UnsubscribeRequest extends Request {
    public function __construct(
        public readonly string $uri,
    ) {
        parent::__construct('resources/unsubscribe');
    }

    public function validate(): void {
        parent::validate();
        if (empty($this->uri)) {
            throw new \InvalidArgumentException('Resource URI cannot be empty');
        }
    }
}

/**
 * Request to list available prompts
 */
class ListPromptsRequest extends PaginatedRequest {
    public function __construct(?string $cursor = null) {
        parent::__construct('prompts/list', $cursor);
    }
}

/**
 * Result of listing available prompts
 */
class ListPromptsResult extends PaginatedResult {
    /**
     * @param Prompt[] $prompts
     */
    public function __construct(
        public readonly array $prompts,
        ?string $nextCursor = null,
        ?array $meta = null,
    ) {
        parent::__construct($nextCursor, $meta);
    }

    public function validate(): void {
        parent::validate();
        foreach ($this->prompts as $prompt) {
            if (!$prompt instanceof Prompt) {
                throw new \InvalidArgumentException('Prompts must be instances of Prompt');
            }
            $prompt->validate();
        }
    }
}

/**
 * Request to get a specific prompt
 */
class GetPromptRequest extends Request {
    public function __construct(
        public readonly string $name,
        public ?array $arguments = null,
    ) {
        parent::__construct('prompts/get');
    }

    public function validate(): void {
        parent::validate();
        if (empty($this->name)) {
            throw new \InvalidArgumentException('Prompt name cannot be empty');
        }
    }
}

/**
 * Result of getting a prompt
 */
class GetPromptResult extends Result {
    /**
     * @param PromptMessage[] $messages
     */
    public function __construct(
        public readonly array $messages,
        public ?string $description = null,
        ?array $meta = null,
    ) {
        parent::__construct($meta);
    }

    public function validate(): void {
        parent::validate();
        foreach ($this->messages as $message) {
            if (!$message instanceof PromptMessage) {
                throw new \InvalidArgumentException('Messages must be instances of PromptMessage');
            }
            $message->validate();
        }
    }
}

/**
 * Request to list available tools
 */
class ListToolsRequest extends PaginatedRequest {
    public function __construct(?string $cursor = null) {
        parent::__construct('tools/list', $cursor);
    }
}

/**
 * Result of listing available tools
 */
class ListToolsResult extends PaginatedResult {
    /**
     * @param Tool[] $tools
     */
    public function __construct(
        public readonly array $tools,
        ?string $nextCursor = null,
        ?array $meta = null,
    ) {
        parent::__construct($nextCursor, $meta);
    }

    public function validate(): void {
        parent::validate();
        foreach ($this->tools as $tool) {
            if (!$tool instanceof Tool) {
                throw new \InvalidArgumentException('Tools must be instances of Tool');
            }
            $tool->validate();
        }
    }
}

/**
 * Request to call a tool
 */
class CallToolRequest extends Request {
    public function __construct(
        public readonly string $name,
        public ?array $arguments = null,
    ) {
        parent::__construct('tools/call');
    }

    public function validate(): void {
        parent::validate();
        if (empty($this->name)) {
            throw new \InvalidArgumentException('Tool name cannot be empty');
        }
    }
}

/**
 * Result of a tool call
 */
class CallToolResult extends Result {
    /**
     * @param (TextContent|ImageContent|EmbeddedResource)[] $content
     */
    public function __construct(
        public readonly array $content,
        public ?bool $isError = false,
        ?array $meta = null,
    ) {
        parent::__construct($meta);
    }

    public function validate(): void {
        parent::validate();
        foreach ($this->content as $item) {
            if (!($item instanceof TextContent || $item instanceof ImageContent || $item instanceof EmbeddedResource)) {
                throw new \InvalidArgumentException('Tool call content must be instances of TextContent, ImageContent, or EmbeddedResource');
            }
            $item->validate();
        }
    }
}

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

/**
 * Result of a completion request
 */
class CompleteResult extends Result {
    public function __construct(
        public readonly array $completion,
        ?array $meta = null,
    ) {
        parent::__construct($meta);
    }

    public function validate(): void {
        parent::validate();
        if (!isset($this->completion['values']) || !is_array($this->completion['values'])) {
            throw new \InvalidArgumentException('Completion must have values array');
        }
        if (count($this->completion['values']) > 100) {
            throw new \InvalidArgumentException('Completion values cannot exceed 100 items');
        }
    }
}

/**
 * Request to create a message via sampling
 */
class CreateMessageRequest extends Request {
    /**
     * @param SamplingMessage[] $messages
     */
    public function __construct(
        public readonly array $messages,
        public readonly int $maxTokens,
        public ?array $stopSequences = null,
        public ?string $systemPrompt = null,
        public ?float $temperature = null,
        public ?array $metadata = null,
        public ?ModelPreferences $modelPreferences = null,
        public ?string $includeContext = null,
    ) {
        parent::__construct('sampling/createMessage');
    }

    public function validate(): void {
        parent::validate();
        if (empty($this->messages)) {
            throw new \InvalidArgumentException('Messages array cannot be empty');
        }
        foreach ($this->messages as $message) {
            if (!$message instanceof SamplingMessage) {
                throw new \InvalidArgumentException('Messages must be instances of SamplingMessage');
            }
            $message->validate();
        }
        if ($this->maxTokens <= 0) {
            throw new \InvalidArgumentException('Max tokens must be greater than 0');
        }
        if ($this->includeContext !== null) {
            if (!in_array($this->includeContext, ['allServers', 'none', 'thisServer'])) {
                throw new \InvalidArgumentException('Invalid include context value');
            }
        }
        if ($this->modelPreferences !== null) {
            $this->modelPreferences->validate();
        }
    }
}

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

/**
 * Request to set logging level
 */
class SetLevelRequest extends Request {
    public function __construct(
        public readonly LoggingLevel $level,
    ) {
        parent::__construct('logging/setLevel');
    }
}

/**
 * Union type for client requests
 */
class ClientRequest {
    private Request $request;

    public function __construct(Request $request) {
        if (!($request instanceof InitializeRequest ||
            $request instanceof PingRequest ||
            $request instanceof ListResourcesRequest ||
            $request instanceof ReadResourceRequest ||
            $request instanceof SubscribeRequest ||
            $request instanceof UnsubscribeRequest ||
            $request instanceof ListPromptsRequest ||
            $request instanceof GetPromptRequest ||
            $request instanceof ListToolsRequest ||
            $request instanceof CallToolRequest ||
            $request instanceof SetLevelRequest ||
            $request instanceof CompleteRequest)) {
            throw new \InvalidArgumentException('Invalid client request type');
        }
        $this->request = $request;
    }

    public function getRequest(): Request {
        return $this->request;
    }
}

/**
 * Union type for server requests
 */
class ServerRequest {
    private Request $request;

    public function __construct(Request $request) {
        if (!($request instanceof PingRequest ||
            $request instanceof CreateMessageRequest ||
            $request instanceof ListRootsRequest)) {
            throw new \InvalidArgumentException('Invalid server request type');
        }
        $this->request = $request;
    }

    public function getRequest(): Request {
        return $this->request;
    }
}

?>