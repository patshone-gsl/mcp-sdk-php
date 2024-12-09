# Model Context Protocol SDK for PHP

This package provides a PHP implementation of the [Model Context Protocol](https://modelcontextprotocol.io), allowing applications to provide context for LLMs in a standardized way. It separates the concerns of providing context from the actual LLM interaction.

## Overview

This PHP SDK implements the full MCP specification, making it easy to:
* Build MCP clients that can connect to any MCP server
* Create MCP servers that expose resources, prompts and tools
* Use standard transports like stdio and SSE
* Handle all MCP protocol messages and lifecycle events

Based on the official [Python SDK](https://github.com/modelcontextprotocol/python-sdk) for the Model Context Protocol.

## Installation

You can install the package via composer:

```bash
composer require logiscape/mcp-sdk-php
```

### Requirements
* PHP 8.1 or higher
* ext-curl
* ext-pcntl (optional, recommended for CLI environments)

## Basic Usage

### Creating an MCP Server

Here's a complete example of creating an MCP server that provides prompts:

```php
<?php

require 'vendor/autoload.php';

use Mcp\Server\Server;
use Mcp\Server\ServerRunner;
use Mcp\Types\Prompt;
use Mcp\Types\PromptArgument;
use Mcp\Types\PromptMessage;
use Mcp\Types\TextContent;
use Mcp\Types\Role;
use Mcp\Types\GetPromptResult;

// Create a server instance
$server = new Server('example-server');

// Register prompt handlers
$server->registerHandler('prompts/list', function($params) {
    $prompt = new Prompt(
        name: 'example-prompt',
        description: 'An example prompt template',
        arguments: [
            new PromptArgument(
                name: 'arg1',
                description: 'Example argument',
                required: true
            )
        ]
    );
    return [$prompt];
});

$server->registerHandler('prompts/get', function($params) {
    $name = $params['name'] ?? '';
    $arguments = $params['arguments'] ?? null;

    if ($name !== 'example-prompt') {
        throw new \InvalidArgumentException("Unknown prompt: {$name}");
    }

    return new GetPromptResult(
        messages: [
            new PromptMessage(
                role: Role::USER,
                content: new TextContent(
                    text: 'Example prompt text'
                )
            )
        ],
        description: 'Example prompt'
    );
});

// Create initialization options and run server
$initOptions = $server->createInitializationOptions();
$runner = new ServerRunner($server, $initOptions);
$runner->run();
```

Save this as `example_server.php` and run it:
```bash
php example_server.php
```

### Creating an MCP Client

Here's how to create a client that connects to the example server:

```php
<?php

require 'vendor/autoload.php';

use Mcp\Client\Client;
use Mcp\Client\Transport\StdioServerParameters;

// Create server parameters for stdio connection
$serverParams = new StdioServerParameters(
    command: 'php',  // Executable
    args: ['example_server.php'],  // Optional command line arguments
    env: null  // Optional environment variables
);

// Create client instance
$client = new Client();

try {
    // Connect to the server using stdio transport
    $session = $client->connect(
        commandOrUrl: $serverParams->getCommand(),
        args: $serverParams->getArgs(),
        env: $serverParams->getEnv()
    );

    // List available prompts
    $prompts = $session->listPrompts();
    
    // Get a specific prompt
    $prompt = $session->getPrompt(
        name: 'example-prompt',
        arguments: ['arg1' => 'value']
    );

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
} finally {
    // Ensure we always cleanup
    if (isset($client)) {
        $client->close();
    }
}
```

Save this as `example_client.php` and run it:
```bash
php example_client.php
```

## Documentation

For detailed information about the Model Context Protocol, visit the [official documentation](https://modelcontextprotocol.io).

## Credits

This PHP SDK was developed by:
- [Josh Abbott](https://joshabbott.com)
- Claude 3.5 Sonnet (Anthropic AI model)

Based on the original [Python SDK](https://github.com/modelcontextprotocol/python-sdk) for the Model Context Protocol.

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
