<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class Contancts extends Model
{
    use HasFactory;

    protected $fillable = ['phone_number', 'email', 'linked_id', 'link_precedence'];
    public function validateFields($email, $phoneNumber) {
        if (empty($email)) {
            return "Empty email";
        }
        if (empty($phoneNumber)) {
            return "Empty phone number";
        }
        if (len($phoneNumber) > 10 || len($phoneNumber) < 10) {
            return "Phone number length is invalid";
        } 
    }
    public function insertRecords($contancts) {
        foreach ($contancts as $contanct) {
            self::insert([
                "phone_number" => $contanct['phoneNumber'],
                "email" => $contanct['email'],
                "linked_id" => $contanct['linkedId'],
                "link_precedence" => $contanct['linkPrecedence'],
                "created_at" => date('Y-m-d H:i:s')
            ]);
        }
    }
    public function getUserIdentity($email, $phoneNumber) {
        $isContanctDetailsExistInDb = self::where("email", $email)
            ->orwhere("phone_number", $phoneNumber)
            ->orderBy("id")
            ->get()
            ->toArray();

        if (!$isContanctDetailsExistInDb) {
            // insert the record
            $contanctDetails = self::create([
                "phone_number" => $phoneNumber,
                "email" => $email,
                "linked_precedence" => "primary"
            ]);

            return [
                "primaryContatctId" => $contanctDetails['id'],
                "emails" => [$email],
                "phoneNumbers" => [$phoneNumber],
                "secondaryContactIds" => []
            ];
        }

        $primaryContanctDetails = self::where(function($query) use($phoneNumber, $email){
            $query->where('phone_number', $phoneNumber)
                ->orwhere('email', $email);
        })
        ->where("link_precedence", "primary")
        ->select(
            "id",
            "email",
            "phone_number",
            "linked_id",
            "link_precedence"
        )
        ->orderBy("id")
        ->get()
        ->toArray();

        if (count($primaryContanctDetails) > 1) {
            // when both record are primary
            $primaryContanctId = $primaryContanctDetails[0]['id'];
            $phoneNumbers =  $emails = $secondaryContactIds = [];
            array_push($phoneNumbers, $primaryContanctDetails[0]['phone_number']);
            array_push($emails, $primaryContanctDetails[0]['email']);

            // update other rows as secondary
            foreach ($primaryContanctDetails as $primaryContanctDetail) {
                if ($primaryContanctDetail['id'] != $primaryContanctId) {
                    self::where('id', $primaryContanctDetail['id'])
                    ->update([
                        'link_precedence' => "secondary",
                        "linked_id" => $primaryContanctId
                    ]);

                    if (!in_array($primaryContanctDetail["id"], $secondaryContactIds)) {
                        array_push($secondaryContactIds, $primaryContanctDetail["id"]);
                    }
                    if (!in_array($primaryContanctDetail["phone_number"], $phoneNumbers)) {
                        array_push($phoneNumbers, $primaryContanctDetail["phone_number"]);
                    }
                    if (!in_array($primaryContanctDetail["email"], $emails)) {
                        array_push($emails, $primaryContanctDetail["email"]);
                    }
                }
            }

            return [
                "primaryContatctId" => $primaryContanctDetails[0]['id'],
                "emails" => $emails,
                "phoneNumbers" => $phoneNumbers,
                "secondaryContactIds" => $secondaryContactIds
            ];
        } else if(count($primaryContanctDetails) == 1) {
            $secondaryContanctDetails = self::where("linked_id", $primaryContanctDetails[0]['id'])
                ->select(
                    "id",
                    "email",
                    "phone_number",
                    "linked_id"
                )
                ->get()
                ->toArray();

            $phoneNumbers =  $emails = $secondaryContactIds = [];
            foreach ($secondaryContanctDetails as $secondaryContanctDetail) {
                if (!in_array($secondaryContanctDetail["id"], $secondaryContactIds)) {
                    array_push($secondaryContactIds, $secondaryContanctDetail["id"]);
                }
                if (!in_array($secondaryContanctDetail["phone_number"], $phoneNumbers)) {
                    array_push($phoneNumbers, $secondaryContanctDetail["phone_number"]);
                }
                if (!in_array($secondaryContanctDetail["email"], $emails)) {
                    array_push($emails, $secondaryContanctDetail["email"]);
                }
            }

            return [
                "primaryContatctId" => $primaryContanctDetails[0]['id'],
                "emails" => $emails,
                "phoneNumbers" => $phoneNumbers,
                "secondaryContactIds" => $secondaryContactIds
            ];
        } else {
            // both email and phone no are in secondary
            $contanctDetails = self::where(function($query) use($phoneNumber, $email){
                $query->where('phone_number', $phoneNumber)
                    ->orwhere('email', $email);
            })
            ->select(
                "id",
                "email",
                "phone_number",
                "linked_id",
                "link_precedence"
            )
            ->orderBy("id")
            ->get()
            ->toArray();
            
            $sampleIds = array_unique(array_column($contanctDetails, "id"));
            $secondaryContanctDetails = self::where("linked_id", $contanctDetails[0]['linked_id'])
                ->wherenotin("id", $sampleIds)
                ->select(
                    "id",
                    "email",
                    "phone_number",
                    "linked_id",
                    "link_precedence"
                )
                ->get()
                ->toArray();

            //array_merge($contanctDetails, $secondaryContanctDetails);
            
            // Log::debug($contanctDetails);
            // Log::debug("---------------------------");
            // Log::debug($secondaryContanctDetails);
            $contanctDetails = array_merge($contanctDetails, $secondaryContanctDetails);
            Log::debug($contanctDetails);

            $linkedIds = $phoneNumbers = $emails = $secondaryContactIds = [];
            foreach ($contanctDetails as $contanctDetail) {
                if (!in_array($contanctDetail['linked_id'], $linkedIds)) {
                    array_push($linkedIds, $contanctDetail['linked_id']);
                }
                if (!in_array($contanctDetail['phone_number'], $phoneNumbers)) {
                    array_push($phoneNumbers, $contanctDetail['phone_number']);
                }
                if (!in_array($contanctDetail['email'], $emails)) {
                    array_push($emails, $contanctDetail['email']);
                }
                if (!in_array($contanctDetail['id'], $secondaryContactIds)) {
                    array_push($secondaryContactIds, $contanctDetail['id']);
                }
            }
            // scenario 1 : > 1
            // scenario 2 : = 1
            if (count($linkedIds) == 1) {
                $primaryContanctDetails = self::where("id" , $linkedIds)
                    ->where("link_precedence", "primary")
                    ->select(
                        "id",
                        "email",
                        "phone_number",
                        "linked_id",
                        "link_precedence"
                    )
                    ->first();

                if (!in_array($primaryContanctDetails['phone_number'], $phoneNumbers)) {
                    array_push($phoneNumbers, $primaryContanctDetails['phone_number']);
                }
                if (!in_array($primaryContanctDetails['email'], $emails)) {
                    array_push($emails, $primaryContanctDetails['email']);
                }

                return [
                    "primaryContatctId" => $primaryContanctDetails['id'],
                    "emails" => $emails,
                    "phoneNumbers" => $phoneNumbers,
                    "secondaryContactIds" => $secondaryContactIds
                ];
            }
        }
    }
    public function isContanctDetailsExistInDb($email, $phoneNumber) {
        return self::where("email", $email)
            ->Orwhere("phone_number", $phoneNumber)
            ->get()
            ->count();
    }
}
