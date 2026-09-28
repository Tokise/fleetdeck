<?php

require_once __DIR__ . '/../config.php';

/**
 * Return a shared PDO database connection.
 *
 * Works with:
 * - Railway MySQL using environment variables from config.php
 * - Local MySQL using the fallback values from config.php
 */
function db(): PDO
{
    static $pdo = null;

    // Reuse the existing connection if already created.
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    try {
        // Build MySQL connection string.
        $dsn =
            'mysql:host=' . DB_HOST .
            ';port=' . DB_PORT .
            ';dbname=' . DB_NAME .
            ';charset=' . DB_CHARSET;

        $pdo = new PDO(
            $dsn,
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );

        return $pdo;

    } catch (PDOException $e) {

        // Log the real database error privately.
        error_log('FleetDeck database connection error: ' . $e->getMessage());

        http_response_code(500);

        // Check if request came from an API endpoint.
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        $isApi = str_contains($requestUri, '/api/');

        if ($isApi) {
            header('Content-Type: application/json; charset=utf-8');

            echo json_encode([
                'success' => false,
                'error' => 'Database connection failed.'
            ]);

            exit;
        }

        // Normal browser request.
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta
                name="viewport"
                content="width=device-width, initial-scale=1.0"
            >
            <title>FleetDeck - Database Error</title>

            <style>
                body {
                    font-family: Arial, sans-serif;
                    background: #f5f5f5;
                    margin: 0;
                    padding: 40px 20px;
                }

                .error-container {
                    max-width: 650px;
                    margin: 80px auto;
                    background: white;
                    padding: 30px;
                    border-radius: 10px;
                    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
                }

                h1 {
                    margin-top: 0;
                }

                p {
                    line-height: 1.6;
                }
            </style>
        </head>

        <body>
            <div class="error-container">
                <h1>Database Connection Error</h1>

                <p>
                    FleetDeck could not connect to the database.
                </p>

                <p>
                    Please check the database configuration
                    and try again.
                </p>
            </div>
        </body>
        </html>
        <?php

        exit;
    }
}