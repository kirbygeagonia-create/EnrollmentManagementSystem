<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Something went wrong · SEAIT EMS</title>
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { min-height: 100vh; display: flex; align-items: center; justify-content: center;
           font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
           background: linear-gradient(135deg, #0f2438 0%, #1e3a5f 60%, #24466e 100%); padding: 24px; }
    .card { background: #fff; border-radius: 16px; padding: 48px 40px; max-width: 480px; width: 100%;
            text-align: center; box-shadow: 0 24px 60px rgba(0,0,0,.35); }
    .code { font-size: 64px; font-weight: 800; color: #1e3a5f; line-height: 1; }
    .accent { width: 56px; height: 4px; background: #ff7900; border-radius: 2px; margin: 20px auto; }
    h1 { font-size: 22px; color: #1e293b; margin-bottom: 10px; }
    p { font-size: 15px; line-height: 1.6; color: #64748b; margin-bottom: 12px; }
    ul { text-align: left; margin: 0 0 28px 20px; color: #64748b; font-size: 14px; line-height: 1.8; }
    a.btn { display: inline-block; background: #ff7900; color: #fff; text-decoration: none;
            font-weight: 600; font-size: 15px; padding: 12px 28px; border-radius: 10px; }
    a.btn:hover { background: #e66d00; }
    .brand { color: #cbd5e1; font-size: 12px; letter-spacing: 2px; margin-top: 24px; text-transform: uppercase; }
</style>
</head>
<body>
<div class="card">
    <div class="code">500</div>
    <div class="accent"></div>
    <h1>Something went wrong on our side</h1>
    <p>The system hit an unexpected error while processing your request.
       The issue has been logged for the administrators.</p>
    <ul>
        <li>Refresh the page and try the action again.</li>
        <li>If it keeps failing, note what you were doing and contact the system administrator.</li>
    </ul>
    <a class="btn" href="/dashboard">Back to Dashboard</a>
    <div class="brand">SEAIT · Enrollment Management System</div>
</div>
</body>
</html>
