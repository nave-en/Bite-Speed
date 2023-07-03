<?php

namespace App\Http\Controllers;

use App\Models\Contacts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ContactsController extends Controller
{
    /**
     * Method to get the user identity
     * @param $request input request
     * @return string
     */
    public function identity(Request $request) : string
    {
        $requestData = $request->all();
        if (empty($requestData)) {
            return json_encode([
                "status" => false,
                "error" => "Empty Data provided."
            ]);
        }

        $email = $requestData['email'];
        $phoneNumber = $requestData['phoneNumber'];
        $contactsTable = new Contacts();
        if ($email && gettype($email) != "string") {
            return json_encode([
                "status" => false,
                "error" => "Invalid Data type provided for email."
            ]);
        }
        if ($phoneNumber && gettype($phoneNumber) != "string") {
            return json_encode([
                "status" => false,
                "error" => "Invalid Data type provided for phone number."
            ]);
        }
        $userDetails = $contactsTable->getUserIdentity($email, $phoneNumber);

        return json_encode([
            "contact" => $userDetails
        ]);
    }
}
