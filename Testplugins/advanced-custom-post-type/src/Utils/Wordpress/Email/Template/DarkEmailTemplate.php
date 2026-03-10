<?php

namespace ACPT\Utils\Wordpress\Email\Template;

class DarkEmailTemplate extends AbstractEmailTemplate
{

    public function getName(): string
    {
        return "dark";
    }

    public function skeleton(): string
    {
        return '<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>{{title}}</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    /* Reset */
    body, table, td, p, a {
      margin: 0;
      padding: 0;
      -webkit-font-smoothing: antialiased;
      line-height: 1.3;
      -ms-text-size-adjust: 100%;
      -webkit-text-size-adjust: 100%;
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI",
                   Roboto, Helvetica, Arial, sans-serif;
    }

    body {
      background-color: #0b1220;
      color: #cbd5f5;
      font-size: 16px;
    }
    
    img {
        max-width: 100%;
    }

    /* Typography */
    h1 {
      font-size: 26px;
      line-height: 1.3;
      font-weight: 600;
      color: #f8fafc;
    }

    h2 {
      font-size: 18px;
      line-height: 1.4;
      font-weight: 600;
      color: #e5e7eb;
      margin-bottom: 8px;
    }
    
    h3,h4 {
      font-size: 16px;
      line-height: 1.4;
      font-weight: 600;
      color: #e5e7eb;
      margin-bottom: 8px;
    }

    p {
      font-size: 15px;
      line-height: 1.6;
      margin-bottom: 16px;
    }

    .muted {
      color: #94a3b8;
      font-size: 13px;
    }

    a {
      color: #60a5fa;
      text-decoration: none;
    }

    /* Layout */
    .container {
      max-width: 600px;
      width: 600px;
      background-color: #0f172a;
      border-radius: 8px;
      overflow: hidden;
      border: 1px solid #1e293b;
    }

    .header:not(:empty) {
      padding: 28px 32px;
      border-bottom: 1px solid #1e293b;
      background-color: #0b1220;
      text-align: center;
    }
    
     .logo {
        max-width: 240px;
    }

    .content {
      padding: 32px;
    }

    .footer:not(:empty) {
      padding: 24px 32px;
      background-color: #0b1220;
      border-top: 1px solid #1e293b;
      text-align: center;
    }

    /* Button */
    .btn {
      display: inline-block;
      padding: 12px 22px;
      background-color: #2563eb;
      color: #ffffff !important;
      font-size: 15px;
      font-weight: 600;
      border-radius: 6px;
    }

    /* Feature card */
    .card {
      background-color: #020617;
      border: 1px solid #1e293b;
      border-radius: 6px;
      padding: 16px;
      margin-bottom: 16px;
    }
    
    /* 📱 Mobile styles */
    @media only screen and (max-width: 600px) {
      .container {
        width: 100% !important;
      }

      .header:not(:empty),
      .content,
      .footer:not(:empty) {
        padding: 20px !important;
      }

      h1 {
        font-size: 22px !important;
      }

      h2 {
        font-size: 17px !important;
      }

      p {
        font-size: 15px !important;
      }

      .btn {
        display: block !important;
        width: 100%;
        text-align: center;
        padding: 14px 0 !important;
      }

      .logo {
        max-width: 180px !important;
      }
    }
  </style>
</head>

<body>

  <!-- Background wrapper -->
  <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
    <tr>
      <td align="center" style="padding:32px 0;">

        <!-- Email container -->
        <table class="container" cellpadding="0" cellspacing="0" role="presentation">

          <!-- Header -->
          <tr>
            <td class="header">{{header}}</td>
          </tr>

          <!-- Main content -->
          <tr>
            <td class="content">
                {{body}}
            </td>
          </tr>

          <!-- Footer -->
          <tr>
            <td class="footer">{{footer}}</td>
          </tr>

        </table>
        <!-- End container -->

      </td>
    </tr>
  </table>

</body>
</html>';
    }
}
