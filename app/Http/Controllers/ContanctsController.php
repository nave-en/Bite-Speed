<?php

namespace App\Http\Controllers;

use App\Models\Contancts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ContanctsController extends Controller
{
    public function create(Request $request) {
        $requestData = $request->all();
        (new Contancts())->insertRecords($requestData);
    }
    public function identity(Request $request) {
        $requestData = $request->all();
        if (empty($requestData)) {
            return json_encode([
                "status" => false,
                "error" => "Empty Data provided"
            ]);
        }

        $email = $requestData['email'];
        $phoneNumber = $requestData['phoneNumber'];
        $contanctsTable = new Contancts();
        //$contanctsTable->validateFields($email, $phoneNumber);
        $userDetails = $contanctsTable->getUserIdentity($email, $phoneNumber);
        Log::debug($userDetails);
        return json_encode([
            "contact" => $userDetails
        ]);
    }
}
