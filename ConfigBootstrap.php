<?php

namespace common\components;

use Yii;


/**
 * Description of ConfigBootstrap
 *
 * @author Pawan Kumar
 */
class ConfigBootstrap implements \yii\base\BootstrapInterface
{

    public function bootstrap($app)
    {
        if (!YII_ENV_PROD) {
            return;
        }

        // $this->exposeSensitiveData();

        $secretManager = new SecretManager();
        $dbConfig = $secretManager->get();

        $app->set("db", [
            'class' => 'yii\db\Connection',
            'dsn' => "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['dbname']}",
            'username' => $dbConfig['username'],
            'password' => $dbConfig['password'],
            'charset' => 'utf8',
        ]);

        
    }

    private function exposeSensitiveData()
    {
        if (YII_ENV_PROD) {

            // Enhanced output buffering: Discard suspicious debug output
            ob_start(function ($buffer) {
                // Sanitize sensitive data first
                $patterns = [
                    '/password\s*=\s*[\'"][^\'"]+[\'"]/i',
                    '/secret/i',
                    '/key\s*=\s*[\'"][^\'"]+[\'"]/i',
                    '/api[_-]?key/i',
                    '/token/i',

                    '/host\s*=\s*[\'"][^\'":\/]+[\'"]/i',      // host=localhost or host="db.example.com"
                    '/username\s*=\s*[\'"][^\'"@]+[\'"]/i',   // username=user or username="dbuser"
                    '/dbname\s*=\s*[\'"][^\'":\/]+[\'"]/i',   // dbname=mydb or dbname="my_app_db"
                    '/port\s*=\s*[\'"]?\d+[\'"]?/i',          // port=3306 (no quotes common; catches numeric values)
                    '/(mysql|pgsql|pdo):\/\/[^:]+:([^@]+)@/',  // DSN format: user:pass@host (redacts user/pass)
                ];
                $buffer = preg_replace($patterns, '[REDACTED]', $buffer);

                // Detect and discard common debug patterns (heuristic - may have false positives)
                $debugPatterns = [
                    '/^Array\s*\(/m',  // print_r arrays
                    '/^stdClass Object\s*\(/m',  // print_r objects
                    '/<pre>.*?<\/pre>/s',  // var_dump/var_export output
                    '/^\s*string\(\d+\)\s*".*?"\s*$/m',  // var_dump strings
                    '/^\s*bool\(true|false\)\s*$/m',  // var_dump bools
                    '/^\s*NULL\s*$/m',  // var_dump null
                ];

                foreach ($debugPatterns as $pattern) {
                    if (preg_match($pattern, $buffer)) {
                        Yii::warning("Discarding debug output detected in buffer: " . substr($buffer, 0, 100), __METHOD__);
                        return '';  // Discard the entire buffer chunk
                    }
                }

                // For echo/print: If buffer looks like raw debug (e.g., short dumps), log but allow if not suspicious
                // Note: This won't catch everything, as echo/print don't have unique signatures
                if (trim($buffer) && strlen(trim($buffer)) < 500 && !preg_match('/<html|<body|HTTP/i', $buffer)) {
                    Yii::warning("Suspicious short output detected (possible echo/print misuse): " . trim($buffer), __METHOD__);
                    // Optionally discard: return '';
                    // For now, just log and pass through (to avoid breaking legit output)
                }

                return $buffer;
            });

            // Enhanced shutdown function: Check for abrupt termination
            register_shutdown_function(function () {
                $lastError = error_get_last();
                if ($lastError && in_array($lastError['type'], [E_ERROR, E_USER_ERROR, E_PARSE])) {
                    Yii::error("Shutdown due to fatal error: {$lastError['message']} at {$lastError['file']}:{$lastError['line']}", __METHOD__);
                }

                // Check if output was flushed early (indicative of die/exit misuse)
                // Note: This is approximate; requires manual flush check if needed
                if (ob_get_level() > 0) {
                    $remainingBuffer = ob_get_contents();
                    if (!empty($remainingBuffer)) {
                        Yii::warning("Unflushed buffer on shutdown - possible early termination: " . substr($remainingBuffer, 0, 100), __METHOD__);
                    }
                    ob_end_clean();  // Discard any remaining to prevent partial output
                }
            });

            // Enhanced error handler: Suppress and log debug function notices/warnings
            $originalHandler = set_error_handler(function ($errno, $errstr, $errfile, $errline) use ($originalHandler) {
                // Check for debug function usage in error context
                $debugTriggers = ['print_r', 'var_dump', 'var_export', 'debug_print_backtrace'];
                foreach ($debugTriggers as $trigger) {
                    if (stripos($errstr, $trigger) !== false || (stripos($errfile, 'debug') !== false && $errno & (E_NOTICE | E_WARNING))) {
                        Yii::warning("Debug function misuse detected: $errstr at $errfile:$errline", __METHOD__);
                        return true;  // Suppress the error
                    }
                }

                // For non-debug errors, call original if set
                return $originalHandler ? call_user_func($originalHandler, $errno, $errstr, $errfile, $errline) : false;
            });

            // Additional protections
            ini_set('display_errors', 0);  // Ensure no raw errors shown
            ini_set('log_errors', 1);  // Log errors instead

            // Log activation
            Yii::info("Enhanced SecurityBootstrap active: debug output detection/discarding, error suppression, and shutdown monitoring enabled.", __METHOD__);
        }
    }

}

