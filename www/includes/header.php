<?php
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IoT Telemetrie Dashboard</title>
    <style>
        :root {
            --bg-color: #0f172a;
            --card-bg: #1e293b;
            --text-color: #f8fafc;
            --accent-color: #38bdf8;
            --border-color: #334155;
            --danger: #ef4444;
            --warning: #f59e0b;
            --success: #10b981;
        }

        body {
            font-family: system-ui, -apple-system, sans-serif;
            background-color: var(--bg-color);
            color: var(--text-color);
            margin: 0;
            padding: 20px;
        }

        .container {
            max-width: 1100px;
            margin: 0 auto;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 15px;
            margin-bottom: 25px;
        }

        nav a {
            color: var(--accent-color);
            text-decoration: none;
            font-weight: bold;
            margin-left: 15px;
        }

        nav a:hover {
            text-decoration: underline;
        }

        .card-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .card {
            background-color: var(--card-bg);
            padding: 20px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }

        .card h3 {
            margin-top: 0;
            color: var(--accent-color);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background-color: var(--card-bg);
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 25px;
        }

        th,
        td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        th {
            background-color: #334155;
        }

        .badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 0.85em;
        }

        .badge-OK {
            background-color: var(--success);
            color: #000;
        }

        .badge-WARNUNG {
            background-color: var(--warning);
            color: #000;
        }

        .badge-KRITISCH {
            background-color: var(--danger);
            color: #fff;
        }

        button,
        .btn {
            background: var(--accent-color);
            color: #0f172a;
            border: none;
            padding: 10px 18px;
            font-weight: bold;
            border-radius: 6px;
            cursor: pointer;
        }

        button:hover,
        .btn:hover {
            opacity: 0.9;
        }

        .alert {
            padding: 12px;
            background-color: var(--success);
            color: #000;
            border-radius: 6px;
            margin-bottom: 20px;
            font-weight: bold;
        }
    </style>
</head>

<body>
    <div class="container">
        <header>
            <h1>IoT Telemetrie Dashboard</h1>
            <nav>
                <a href="index.php">Dashboard</a>
                <a href="admin.php">Admin Panel</a>
            </nav>
        </header>