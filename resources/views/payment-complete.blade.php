<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment complete | Altfon</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background: #f5f7fb;
            color: #010a4c;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        }

        .card {
            width: 100%;
            max-width: 420px;
            padding: 40px 28px;
            border-radius: 18px;
            background: #ffffff;
            box-shadow: 0 18px 50px rgba(1, 10, 76, 0.12);
            text-align: center;
        }

        .mark {
            width: 64px;
            height: 64px;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: #eefaf1;
            color: #1f8f4a;
            font-size: 30px;
            font-weight: 700;
        }

        h1 {
            margin: 0 0 10px;
            font-size: 24px;
            line-height: 1.3;
        }

        p {
            margin: 0;
            color: #5b6478;
            font-size: 15px;
            line-height: 1.6;
        }

        .note {
            margin-top: 18px;
            font-size: 13px;
            color: #7b8497;
        }

        .button {
            display: inline-block;
            margin-top: 26px;
            padding: 13px 24px;
            border: 0;
            border-radius: 10px;
            background: #f29619;
            color: #ffffff;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <main class="card">
        <div class="mark">&#10003;</div>
        <h1>Payment received</h1>
        <p>Your Altfon credit purchase is being confirmed.</p>
        <p class="note">You can close this page and return to the Altfon app.</p>
        <button class="button" type="button" onclick="window.close()">Return to Altfon</button>
    </main>
</body>
</html>
