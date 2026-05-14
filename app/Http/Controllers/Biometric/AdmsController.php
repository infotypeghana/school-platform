<?php

namespace App\Http\Controllers\Biometric;

use App\Http\Controllers\Controller;
use App\Services\BiometricAttendanceService;
use App\Services\ZKTecoService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * ZKTeco ADMS (Automatic Data Management System) endpoint.
 *
 * Configure on the device:
 *   Server IP / Domain : your-school.schoolms.com.gh
 *   Server Port        : 80 (or 443)
 *   Device→Server Path : /biometric/adms
 *
 * The device calls two endpoints:
 *
 *   1. GET  /biometric/adms?SN=XXXX&options=all
 *      → Server acknowledges and sends optional config
 *
 *   2. POST /biometric/adms?SN=XXXX&table=ATTLOG&Stamp=N
 *      Body: "user_id\tdatetime\tverify_type\tdirection\r\n" per record
 *      → Server parses, creates attendance records, replies "OK: N"
 *
 *   3. GET  /biometric/adms?SN=XXXX&table=OPERLOG
 *      → We respond with no pending commands
 */
class AdmsController extends Controller
{
    public function __construct(
        private readonly BiometricAttendanceService $bio,
        private readonly ZKTecoService $zk,
    ) {}

    /**
     * Handle GET: device check-in / config request.
     */
    public function get(Request $request): Response
    {
        $serial = $request->query('SN', '');
        $device = $this->bio->findDeviceBySerial($serial);

        if (! $device) {
            // Unknown device — acknowledge but do nothing
            return response("OK\n", 200)->header('Content-Type', 'text/plain');
        }

        // Respond with ADMS config / handshake
        // ATTStamp=0 means "send all records from the beginning"
        $body = implode("\n", [
            "GET OPTION FROM: {$serial}",
            "ATTStamp=0",
            "",
        ]);

        return response($body, 200)->header('Content-Type', 'text/plain');
    }

    /**
     * Handle POST: device is pushing attendance data.
     */
    public function post(Request $request): Response
    {
        $serial = $request->query('SN', '');
        $table  = $request->query('table', '');

        $device = $this->bio->findDeviceBySerial($serial);

        if (! $device) {
            return response("ERROR\n", 403)->header('Content-Type', 'text/plain');
        }

        if ($table !== 'ATTLOG') {
            // Ignore non-attendance tables (OPERLOG, USERINFO, etc.)
            return response("OK: 0\n", 200)->header('Content-Type', 'text/plain');
        }

        $body    = $request->getContent();
        $punches = ZKTecoService::parseAdmsPayload($body);

        if (empty($punches)) {
            return response("OK: 0\n", 200)->header('Content-Type', 'text/plain');
        }

        $result = $this->bio->ingest($device, $punches);

        $count = $result['imported'] + $result['skipped'];

        return response("OK: {$count}\n", 200)->header('Content-Type', 'text/plain');
    }
}
