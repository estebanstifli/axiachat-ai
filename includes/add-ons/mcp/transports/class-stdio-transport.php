<?php
/**
 * STDIO Transport for MCP
 * 
 * Implements MCP over STDIO for local process communication.
 * Uses proc_open() to spawn and communicate with local MCP servers.
 * 
 * @package AIChat
 * @subpackage MCP
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class AIChat_MCP_STDIO_Transport extends AIChat_MCP_Transport {
    
    /**
     * Process resource
     * @var resource|null
     */
    private $process = null;
    
    /**
     * Process pipes [stdin, stdout, stderr]
     * @var array
     */
    private $pipes = [];
    
    /**
     * Command to execute
     * @var string
     */
    private $command = '';
    
    /**
     * Command arguments
     * @var array
     */
    private $args = [];
    
    /**
     * Working directory
     * @var string|null
     */
    private $cwd = null;
    
    /**
     * Constructor
     * 
     * @param array $config Configuration:
     *   - command: string Executable path
     *   - args: array Command arguments
     *   - cwd: string Working directory (optional)
     */
    public function __construct( array $config = [] ) {
        parent::__construct( $config );
        
        $this->command = isset( $config['command'] ) ? sanitize_text_field( $config['command'] ) : '';
        $this->args    = isset( $config['args'] ) && is_array( $config['args'] ) ? $config['args'] : [];
        $this->cwd     = isset( $config['cwd'] ) ? sanitize_text_field( $config['cwd'] ) : null;
    }
    
    /**
     * Spawn MCP server process
     * 
     * @return bool
     */
    public function connect() {
        if ( empty( $this->command ) ) {
            $this->last_error = 'No command configured';
            return false;
        }
        
        // Check if proc_open is available
        if ( ! function_exists( 'proc_open' ) ) {
            $this->last_error = 'proc_open() not available - check disable_functions in php.ini';
            return false;
        }
        
        // Build full command with args
        $full_command = escapeshellcmd( $this->command );
        
        foreach ( $this->args as $arg ) {
            $full_command .= ' ' . escapeshellarg( $arg );
        }
        
        $this->log_debug( 'Spawning STDIO process', [
            'command' => $full_command,
            'cwd'     => $this->cwd,
        ] );
        
        // Pipe descriptors: stdin, stdout, stderr
        $descriptors = [
            0 => [ 'pipe', 'r' ], // stdin - write
            1 => [ 'pipe', 'w' ], // stdout - read
            2 => [ 'pipe', 'w' ], // stderr - read
        ];
        
        // Spawn process
        $this->process = proc_open(
            $full_command,
            $descriptors,
            $this->pipes,
            $this->cwd
        );
        
        if ( ! is_resource( $this->process ) ) {
            $this->last_error = 'Failed to spawn process';
            return false;
        }
        
        // Set non-blocking mode for stdout and stderr
        stream_set_blocking( $this->pipes[1], false );
        stream_set_blocking( $this->pipes[2], false );
        
        $this->connected = true;
        
        $this->log_debug( 'STDIO process spawned successfully' );
        
        return true;
    }
    
    /**
     * Send JSON-RPC request via STDIO
     * 
     * @param string      $method
     * @param array       $params
     * @param string|null $id
     * @return array|null
     */
    public function send_request( $method, $params = [], $id = null ) {
        if ( ! $this->connected || ! is_resource( $this->process ) ) {
            $this->last_error = 'Not connected';
            return null;
        }
        
        // Auto-generate ID if needed
        if ( $id === null && strpos( $method, 'notifications/' ) !== 0 ) {
            $id = $this->generate_request_id();
        }
        
        $request_payload = $this->build_jsonrpc_request( $method, $params, $id );
        $request_json    = wp_json_encode( $request_payload );
        
        $this->log_debug( 'Sending STDIO request', [
            'method' => $method,
            'size'   => strlen( $request_json ) . ' bytes',
        ] );
        
        // Write to stdin (newline-delimited)
        $written = fwrite( $this->pipes[0], $request_json . "\n" );
        fflush( $this->pipes[0] );
        
        if ( $written === false ) {
            $this->last_error = 'Failed to write to stdin';
            return null;
        }
        
        // For notifications, don't wait for response
        if ( $id === null ) {
            return [ 'notification' => true ];
        }
        
        // Read response from stdout (newline-delimited)
        $response_line = $this->read_line_with_timeout( $this->pipes[1], 10 );
        
        if ( $response_line === null ) {
            $this->last_error = 'Timeout waiting for response';
            
            // Check stderr for errors
            $stderr_output = stream_get_contents( $this->pipes[2] );
            if ( ! empty( $stderr_output ) ) {
                $this->last_error .= ' - stderr: ' . $stderr_output;
            }
            
            return null;
        }
        
        $this->log_debug( 'STDIO response received', [
            'size' => strlen( $response_line ) . ' bytes',
        ] );
        
        return $this->parse_jsonrpc_response( $response_line );
    }
    
    /**
     * Read a line from stream with timeout
     * 
     * @param resource $stream
     * @param int      $timeout_seconds
     * @return string|null
     */
    private function read_line_with_timeout( $stream, $timeout_seconds = 10 ) {
        $start_time = time();
        $buffer     = '';
        
        while ( ( time() - $start_time ) < $timeout_seconds ) {
            $chunk = fgets( $stream );
            
            if ( $chunk === false ) {
                // No data available, sleep briefly
                usleep( 50000 ); // 50ms
                continue;
            }
            
            $buffer .= $chunk;
            
            // Check if we have a complete line
            if ( substr( $chunk, -1 ) === "\n" ) {
                return trim( $buffer );
            }
        }
        
        return null; // Timeout
    }
    
    /**
     * Close process and pipes
     * 
     * @return bool
     */
    public function close() {
        if ( ! is_resource( $this->process ) ) {
            return true;
        }
        
        $this->log_debug( 'Closing STDIO process' );
        
        // Close pipes
        foreach ( $this->pipes as $pipe ) {
            if ( is_resource( $pipe ) ) {
                fclose( $pipe );
            }
        }
        
        // Close process
        $exit_code = proc_close( $this->process );
        
        $this->process   = null;
        $this->pipes     = [];
        $this->connected = false;
        
        $this->log_debug( 'STDIO process closed', [ 'exit_code' => $exit_code ] );
        
        return true;
    }
    
    /**
     * Get transport type
     * 
     * @return string
     */
    public function get_type() {
        return 'stdio';
    }
    
    /**
     * Destructor - ensure process is closed
     */
    public function __destruct() {
        $this->close();
    }
}
