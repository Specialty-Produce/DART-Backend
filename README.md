# DART Backend

PHP web services behind **DART**, Specialty Produce's delivery app for drivers. Drivers use the iOS app (iPad/iPhone) to log in, load their route, run the pre-trip and post-trip truck checklists, record deliveries and signatures, take photos, and clock breaks. Every one of those actions calls a PHP script in this repo. The scripts read and write the Specialty Produce SQL Server database, mostly through `uspDART*` stored procedures.

## How it works

- **One script per endpoint.** Each top-level `.php` file is a single web service, such as `login.php` or `getdriverroute.php`. There is no router or framework.
- **Requests** are HTTP `POST`s. Newer endpoints take one form field, `jsondata`, which holds a JSON object (for example, `login.php` expects `username`, `password`, `udid`, `devicename`, `apntoken` and `version`). Simpler endpoints read individual form fields such as `userid`.
- **Responses** are JSON, built on the global `$sendObj` defined in [dart_init.php](dart_init.php):

  ```json
  {
    "webservice": "getdriverroute.php",
    "status": "success",
    "statusCode": 200,
    "data": { }
  }
  ```

  On failure, `sendError()` sets `status` to `"error"`, sets the HTTP status code, and adds an `error` object with `code`, `retry`, `userMessage`, `systemMessage` and `timestamp`. The `code` values come from the `ERROR_CODES` interface in `dart_init.php` (`ERROR_DATABASE`, `ERROR_INVALID_USER`, `ERROR_MAINTENANCE_MODE`, and others).
- **Database access** uses `new PDO('spdb', '', '')`, a DSN alias that the server resolves. Database calls sit in a retry loop: on a timeout, a connection failure or a deadlock, the script waits `DART_SQL_TIMEOUT_SLEEP` seconds and tries again, up to `DART_SQL_TIMEOUT_MAX_TRIES` times.
- **Logging.** `dartLogging()` appends to `DART_LOG_DIR/<script>.log`. Each request gets a random 6-character code (`$codeStr`), so you can pick out one request's lines when several requests write to the same log at once.

### Standard script skeleton

New endpoints should follow the pattern the existing ones use:

```php
<?php
include_once 'global_CDC.php';
include 'dart_init.php';
$currentScript = basename($_SERVER["SCRIPT_NAME"]);
$sendObj->webservice = $currentScript;
$codeStr = generateRandomCode(6);

// 1. Validate input -> sendError(400, ERROR_CODES::ERROR_INVALID_DATA, ...) and exit()
// 2. Run the stored procedure(s) inside the SQL retry loop
// 3. Fill $sendObj->data and call sendResult()
```

## Dependencies

### Shared include library (outside this repo)

Every script begins with `include_once 'global_CDC.php'`, and many scripts load classes from `classes_SP/`. These files are **not in this repo**. They live in the shared PHP include library (`C:\PHP8\includes` on development machines), and PHP's `include_path` must point to that library.

`global_CDC.php` supplies helpers used throughout the code, such as `generateRandomCode()` and `SP_ErrorLogging()`.

DART uses the following classes:

| Class | Used for |
|---|---|
| `class_DART.php` | DART helpers: driver lists, `DART::click` tracking, poor-quality alerts |
| `class_ADPWFN_SP.php` | ADP Workforce Now punches (break start/end, punch-in, login) |
| `class_Samsara_SP.php` | Assigning a driver to a vehicle in Samsara at the start checklist |
| `class_SendGridSP.php` | Invoice emails and the SendGrid webhook |
| `class_AzureBlobSP.php` / `class_AzureFileSP.php` | Storage for delivery and product photos, and for invoice files |
| `class_LocationSP.php` | Customer location GPS, notes, contacts and COG setup |
| `class_InvoiceSP.php` | Invoice generation |
| `class_APN_SP.php` | Apple push notifications |
| `class_RoutingGTM.php` | Route optimization |
| `class_SP_SFTP.php` | SFTP delivery of invoice files |
| `class_TwilioSMS.php` | SMS driver reminders |
| `class_PHPMailerSP.php` | Email from admin jobs |
| `class_SP_Exception.php` | Shared exception type |
| `EDI_SP/Receivers/` | EDI invoice formats used by `sendinvoice.php` |

### Runtime

- PHP 8 with PDO and a SQL Server driver
- SQL Server with a PDO DSN alias named `spdb`
- The writable directories defined in `dart_init.php` (logs, signatures, and invoice PDF/RSI/PPP/PlateIQ/CT output). Their paths use the Azure App Service layout (`/home/site/...`).

## Configuration

All configuration lives in [dart_init.php](dart_init.php):

| Setting | Purpose |
|---|---|
| `MAINTENANCE_MODE` | When set to `true`, the app is told the backend is in maintenance mode |
| `DEBUG_USERID` | The test driver. For this user, `getdriverroute.php` reads fixed test tables instead of a live route (see [Debug tools](#debug-and-info-tools-browser)) |
| `PRINTED_INVOICE_ID`, `DARK_STOP_ID`, `DELIVERY_NO_SIGNATURE_ID` | Special user IDs recorded as the "signer" for printed invoices, dark stops, and deliveries with no signature |
| `GREEN_DISCOUNT_ID`, `SALES_TAX_ID` | Product IDs for the green discount and sales tax lines |
| `DART_*_DIR` | Paths for logs, signatures, and invoice output |
| `DART_SQL_TIMEOUT_*` | SQL retry behaviour |
| `DART_SENDINVOICE_API_KEY` | The `X-API-Key` value that `sendinvoice.php` requires |

## Endpoints

### Session and reference data

| Script | Purpose |
|---|---|
| `login.php` | Authenticates a driver (`uspDARTLogin`), records the device and APN token, and returns the driver's ADP punches |
| `getstaticinfo.php` | Lookup lists: drivers, vehicles, menu, order status, product status, and sent-back reasons |
| `getdartversions.php` | The latest released iPad, iPhone and iPhone DB versions, so the app can prompt for updates |
| `getstartchecklist.php` | The vehicle list for the start-of-day checklist |
| `getendchecklist.php` | Returns the HTML of `endchecklist.htm` for the app to display |
| `getaccidentreport.php` | Returns the HTML of `accidentreport.htm` for the app to display |

### Route

| Script | Purpose |
|---|---|
| `getdriverroute.php` | Assigns invoices to the driver and returns the route and its signers |
| `getdriverinvoices.php` | The driver's invoices |
| `getinvoices.php` | Invoice detail for a list of sale IDs |
| `getpullreport.php` | The driver's pull (picking) report |
| `validateinvoices.php` | Checks that the app's invoices still match the server |
| `startroute.php` | Marks the route as started |
| `optimizeRoute.php` | Optimizes the stop order for a set of locations |
| `updatedeliverysort.php` | Saves the driver's stop order or the optimized stop order |
| `transferdriver.php` | Moves an invoice to another driver |
| `endroute.php` | Logs the end-of-route call |

### Checklists

| Script | Purpose |
|---|---|
| `putstartchecklist.php` | Saves the pre-trip checklist and truck data, and assigns the vehicle in Samsara |
| `putendchecklist.php` | Saves the post-trip checklist, truck data and temperature log, and ends the invoice assignment |
| `putaccidentreport.php` | Submits an accident report |

### Deliveries

| Script | Purpose |
|---|---|
| `updateatdelivery.php` | Marks invoices as currently being delivered |
| `unupdateatdelivery.php` | Reverses `updateatdelivery.php` |
| `deliverycomplete.php` | Completes a delivery: signature, line-item changes, sent-back items, green discount, sales tax, and the invoice email |
| `deliverednosignature.php` | Completes a delivery that has no signature |
| `deliverycompleteWarehouse.php` | Completes a warehouse (will-call) delivery with a signature |
| `redx.php` | Records a sent-back (red X) item with a note |
| `cancelledbysp.php` | Marks a sale as cancelled by Specialty Produce |
| `printdriverpaperwork.php` | Records delivery data for printed paperwork |
| `signerdelete.php` | Removes a signer from a location |
| `putpicturedelivery.php` | Uploads a delivery photo to Azure Blob storage |
| `putpicture.php` | Uploads a poor-quality product photo and alerts staff |
| `updatelocationgps.php` | Updates a customer location's GPS position and delivery notes |

### Time and GPS

| Script | Purpose |
|---|---|
| `breakstart.php` / `breakend.php` | Records a break (`uspDARTBreakTime`) and submits the punch to ADP |
| `putpunchin.php` | Records a punch-in and submits it to ADP |
| `putgpsdata.php` | Receives GPS breadcrumbs from the device |

### Invoices and integrations

| Script | Purpose |
|---|---|
| `sendinvoice.php` | Builds invoice PDFs and EDI files, then sends them by email, SFTP or blob storage. Callers must send the `X-API-Key` header |
| `faxinvoices.php` | Processes the queue of pending invoice faxes |
| `adhoc_apnsend.php` | Sends a push notification by hand |
| `apnconfirm.php` | Logs push notification confirmations from the app |

### Diagnostics

| Script | Purpose |
|---|---|
| `index.php` | Status page showing the server time, your IP, and the PHP and SQL Server versions |
| `cfHealthCheck.php` | Cloudflare health check. Returns 200 when the database is reachable and 500 when it is not |
| `errorreport.php`, `logreport.php`, `crashreporter.php` | Store error reports, logs and crash reports sent by the app |

### Debug and info tools (browser)

You open these in a browser, not from the app.

| Script | Purpose |
|---|---|
| `currentUsers.php` | Today's DART users, with app version, iPad and vehicle |
| `checkdriver.php` | Look up a driver's route by username |
| `setdriverstate.php` / `setstate.php` / `debugsetsyncstate.php` | Change the test driver's sync state in `driverstate.txt`. `0` means the original tables and `1` means the rsync tables |

### Possibly unused

These scripts have not been converted to the current JSON response format (most still return the old XML-style status). They may no longer be called by the production app. Confirm whether a script is still used before changing or removing it.

`loginIpads.php`, `lunchbreakstart.php`, `lunchbreakend.php`, `putnewsigner.php`, `removefromdart.php`, `sigresend.php`, `startchecklist.php`, `statusupdate.php`

## Other directories

| Path | Contents |
|---|---|
| `adm/` | Scheduled and admin jobs. `dailyCleanup.php` archives the logs and emails the error log. `sgWebhookCallback.php` receives SendGrid event webhooks. `processSigs.php` is disabled (it exits at the start) |
| `include/` | APN and SSL certificates, the invoice XML templates, and `backupSigProcess.php` |
| `include/tasks/` | `driverReminders.php`, which texts reminders to drivers based on their ADP punches |
| `dev/` | Development-only pages for creating test invoices and listing invoices |
| `scripts/` | MooTools and `dart.js`, used by the browser debug pages |
| `*.htm` / `*.html` | Checklist and accident report forms served to the app |
| `ToDo.txt` | Progress notes on converting the endpoints to JSON |

## Deployment

The app is hosted on **Azure App Service** as the `spdart` app, which has a `dev` slot.

Deployment is manual, using the VS Code **SFTP** extension. [.vscode/sftp.json](.vscode/sftp.json) defines two profiles, `dev` (the default) and `prod`, and both upload to `/site/wwwroot` over FTPS. Credentials are not in the repo. Ask the team for them.

1. Switch to the `dev` profile, upload your changes, and test them against a DART build that points at dev.
2. Switch to the `prod` profile and upload the same files.

[.htaccess](.htaccess) serves `robots-dev.txt` on `dev.specialtyproduce.com`, strips version numbers from `.css`/`.js` URLs (`file.123.js` → `file.js`), and includes a commented-out maintenance-mode rewrite.

## Source control

Git is a **git-svn** mirror of `svn/Specialty/DART/trunk`, so commit through SVN. Use `git svn rebase` to pull changes and `git svn dcommit` to push them.

## Things to know

- **SQL is built with string interpolation**, as in `"uspDARTLogin '$username', ..."`. Validate and escape inputs carefully. For new code, prefer prepared statements.
- **Secrets are committed to the repo:** the `sendinvoice.php` API key in `dart_init.php`, and the APN and SSL `.pem` files in `include/`. Don't add more. If any of them are rotated, update the server as well.
- `DEBUG_USERID` gets special behaviour in several endpoints. When a problem only shows up for the test driver, check `driverstate.txt` first.
- Logs are written to `DART_LOG_DIR` on the server (`/home/site/wwwroot/logs/`), with one file per script.
