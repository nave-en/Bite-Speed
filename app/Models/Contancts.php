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
            if ($phoneNumber == NULL || $email == NULL) {
                return ["error" => "Input Fields cannot be null"];
            }
            // insert the record
            $contanctDetails = self::create([
                "phone_number" => $phoneNumber,
                "email" => $email,
                "link_precedence" => "primary"
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
                }
            }

            $secondaryContanctDetails = self::where("linked_id", $primaryContanctId)
                ->select(
                    'id',
                    'phone_number',
                    'email'
                )
                ->get()
                ->toArray();

            foreach ($secondaryContanctDetails as $secondaryContanctDetail) {
                if (!(in_array($secondaryContanctDetail['phone_number'], $phoneNumbers))) {
                    array_push($phoneNumbers, $secondaryContanctDetail['phone_number']);
                }
                if (!in_array($secondaryContanctDetail['email'], $emails)) {
                    array_push($emails, $secondaryContanctDetail['email']);
                }
                array_push($secondaryContactIds, $secondaryContanctDetail['id']);
            }

            return [
                "primaryContatctId" => $primaryContanctId,
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
            array_push($phoneNumbers, $primaryContanctDetails[0]['phone_number']);
            array_push($emails, $primaryContanctDetails[0]['email']);
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

            if ((!in_array($phoneNumber, $phoneNumbers))
                && ($phoneNumber != null)
             ) {
                $newRecord = self::create([
                    "phone_number" => $phoneNumber,
                    "email" => $email,
                    "link_precedence" => "secondary",
                    "linked_id" => $primaryContanctDetails[0]["id"]
                ]);
                array_push($phoneNumbers, $phoneNumber);
                array_push($secondaryContactIds, $newRecord['id']);
            }
            if (
                ($email != null)
                && (!in_array($email, $emails))
            ){
                $newRecord = self::create([
                    "phone_number" => $phoneNumber,
                    "email" => $email,
                    "link_precedence" => "secondary",
                    "linked_id" => $primaryContanctDetails[0]["id"]
                ]);
                array_push($emails, $email);
                array_push($secondaryContactIds, $newRecord['id']);
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
            
            $linkedIds = array_unique(array_column($contanctDetails, "linked_id"));
            if (count($linkedIds) == 1) {
                $primaryContanctDetails = self::where("id", $linkedIds)
                    ->select(
                        "id",
                        "email",
                        "phone_number",
                        "linked_id",
                        "link_precedence"
                    )
                    ->first();
                
                $additioanlSecondaryContanctDetails = self::where("linked_id", $linkedIds)
                    ->select(
                        "id",
                        "email",
                        "phone_number",
                        "linked_id",
                        "link_precedence"
                    )
                    ->get()
                    ->toArray();

                if ($additioanlSecondaryContanctDetails) {
                    $contanctDetails = array_merge($contanctDetails, $additioanlSecondaryContanctDetails);
                }

                $phoneNumbers = $emails = $secondaryContactIds = [];
                foreach ($contanctDetails as $contanctDetail) {
                    if (!in_array($contanctDetail['phone_number'], $phoneNumbers)) {
                        array_push($phoneNumbers, $contanctDetail['phone_number']);
                    }
                    if (!in_array($contanctDetail['email'], $emails)) {
                        array_push($emails, $contanctDetail['email']);
                    }

                    if  (!in_array($contanctDetail['id'], $secondaryContactIds)) {
                        array_push($secondaryContactIds, $contanctDetail['id']);
                    }
                }
                
                sort($secondaryContactIds);
                return [
                    "primaryContatctId" => $primaryContanctDetails['id'],
                    "emails" => $emails,
                    "phoneNumbers" => $phoneNumbers,
                    "secondaryContactIds" => $secondaryContactIds
                ];
            } else {
                // need to update
                sort($linkedIds);
                // first linked id is primary
                $primaryContanctDetails = self::where("id", $linkedIds[0])
                    ->select(
                        "id",
                        "email",
                        "phone_number",
                        "linked_id",
                        "link_precedence"
                    )
                    ->first();
                
                $additionalIds = [];
                for($i = 1; $i < count($linkedIds); $i++) {
                    array_push($additionalIds, $linkedIds[$i]);
                }

                Log::debug($additionalIds);
                self::whereIn('linked_id', $additionalIds)
                    ->update([
                            'linked_id' => $linkedIds[0]
                    ]);
                self::whereIn('id', $additionalIds)
                    ->update([
                        'linked_id' => $linkedIds[0],
                        'link_precedence' => "secondary"            
                    ]);

                $additionalSecondaryContactDetails = self::where("linked_id", $linkedIds[0])
                    ->select(
                        "id",
                        "email",
                        "phone_number",
                        "linked_id",
                        "link_precedence"
                    )
                    ->get()
                    ->toArray();

                $contanctDetails = array_merge($contanctDetails, $additionalSecondaryContactDetails);
                $emails = $phoneNumbers = $secondaryContactIds = [];
                array_push($emails, $primaryContanctDetails['email']);
                array_push($phoneNumbers, $primaryContanctDetails['phone_number']);
                foreach($contanctDetails as $contanctDetail) {
                    if (!in_array($contanctDetail['id'], $secondaryContactIds)) {
                        array_push($secondaryContactIds, $contanctDetail['id']);
                    }
                    if (!in_array($contanctDetail['phone_number'], $phoneNumbers)) {
                        array_push($phoneNumbers, $contanctDetail['phone_number']);
                    }
                    if (!in_array($contanctDetail['email'], $emails)) {
                        array_push($emails, $contanctDetail['email']);
                    }
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
