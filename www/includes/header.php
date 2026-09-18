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
            --muted-color: #94a3b8;
            --accent-color: #38bdf8;
            --border-color: #334155;
            --danger: #ef4444;
            --warning: #f59e0b;
            --success: #10b981;
            --secondary: #475569;
        }

        body {
            font-family: system-ui, -apple-system, sans-serif;
            background-color: var(--bg-color);
            color: var(--text-color);
            margin: 0;
            padding: 20px;
        }

        .container {
            max-width: 90%;
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

        header h1 {
            margin: 0;
            font-size: 1.8rem;
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

        /* Layout & Utilities */
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

        .flex-between {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .flex-wrap-end {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: flex-end;
        }

        .flex-gap-2 {
            display: flex;
            gap: 8px;
        }

        .text-muted {
            color: var(--muted-color);
            font-size: 0.9em;
        }

        .mb-2 {
            margin-bottom: 10px;
        }

        .mb-3 {
            margin-bottom: 15px;
        }

        .mb-4 {
            margin-bottom: 25px;
        }

        .mt-2 {
            margin-top: 10px;
        }

        .mt-3 {
            margin-top: 15px;
        }

        /* Tables */
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

        /* Form Controls */
        .form-group {
            margin-bottom: 12px;
        }

        .form-group label {
            display: block;
            margin-bottom: 4px;
            font-size: 0.9em;
            color: var(--muted-color);
        }

        .form-control,
        .form-select,
        .form-textarea {
            width: 100%;
            padding: 8px 12px;
            border-radius: 4px;
            background: #0f172a;
            color: #fff;
            border: 1px solid var(--border-color);
            box-sizing: border-box;
            font-size: 0.95em;
        }

        .form-control:focus,
        .form-select:focus,
        .form-textarea:focus {
            outline: none;
            border-color: var(--accent-color);
        }

        .form-control-sm {
            padding: 4px 8px;
            font-size: 0.85em;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 8px;
            align-items: center;
        }

        .metric-builder-row {
            background: #0f172a;
            padding: 10px;
            border-radius: 6px;
            border: 1px solid var(--border-color);
            margin-bottom: 10px;
        }

        /* Badges & Pills */
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

        .metric-pill {
            background: #0f172a;
            padding: 3px 8px;
            border-radius: 4px;
            border: 1px solid var(--border-color);
            font-size: 0.85em;
            margin-right: 4px;
            display: inline-block;
        }

        .token-code {
            font-family: monospace;
            background: #0f172a;
            padding: 4px 8px;
            border-radius: 4px;
            border: 1px solid var(--border-color);
            font-size: 0.85em;
            color: var(--accent-color);
            user-select: all;
        }

        /* Buttons */
        button,
        .btn {
            background: var(--accent-color);
            color: #0f172a;
            border: none;
            padding: 8px 14px;
            font-weight: bold;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 0.9em;
        }

        button:hover,
        .btn:hover {
            opacity: 0.9;
        }

        .btn-secondary {
            background: var(--secondary);
            color: #fff;
        }

        .btn-success {
            background: var(--success);
            color: #000;
        }

        .btn-warning {
            background: var(--warning);
            color: #000;
        }

        .btn-danger {
            background: var(--danger);
            color: #fff;
        }

        .btn-sm {
            padding: 4px 8px;
            font-size: 0.8em;
        }

        /* Alerts */
        .alert {
            padding: 12px;
            background-color: var(--success);
            color: #000;
            border-radius: 6px;
            margin-bottom: 20px;
            font-weight: bold;
        }

        .alert-danger {
            background-color: var(--danger);
            color: #fff;
        }

        .chart-box {
            height: 220px;
            position: relative;
        }

        .chart-box-lg {
            height: 300px;
            position: relative;
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