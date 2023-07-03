<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class contacts extends Model
{
    use HasFactory;

    protected $fillable = ['phone_number', 'email', 'linked_id', 'link_precedence'];
    const PRIMARY = "primary";
    const SECONDARY = "secondary";
    const ID = "id";
    const EMAIL = "email";
    const PHONENUMBER = "phone_number";
    const LINKEDID = "linked_id";
    const LINKEDPRECEDENCE = "link_precedence";

    /**
     * Method to check contact details already exist in the database
     * @param string $email
     * @param string $phoneNumber
     * @return bool
     */
    public function isContactAlreadyExist($email, $phoneNumber) : bool
    {
        $rowCount = self::where("email", $email)
            ->orwhere("phone_number", $phoneNumber)
            ->orderBy("id")
            ->get()
            ->count();
        
        if ($rowCount == 0) {
            return false;
        }

        return true;
    }

    /**
     * Method to create new record in the database
     * @param string $email
     * @param string $phoneNumber
     * @param bool $isPrimary
     * @param int $primaryContactId
     * @return array
     */
    public function createRecords($email, $phoneNumber, $isPrimary, $primaryContactId = null) : array
    {
        $recordTocreate = [];
        if ($isPrimary) {
            $recordTocreate = [
                self::PHONENUMBER => $phoneNumber,
                self::EMAIL => $email,
                self::LINKEDPRECEDENCE => self::PRIMARY
            ];
        } else {
            $recordTocreate = [
                self::PHONENUMBER => $phoneNumber,
                self::EMAIL => $email,
                self::LINKEDPRECEDENCE => self::SECONDARY,
                self::LINKEDID => $primaryContactId
            ];
        }
        try {
            $contactDetails = self::create($recordTocreate);

            $contactDetails["status"] = true;
        } catch (\Error $err) {
            Log::error(
                "Failed to create new record in the datbase for the record" . json_encode($recordTocreate) .
                " and  the error : " . $err->getmessage()
            );

            $contactDetails["status"] = false;
        }

        return json_decode($contactDetails, true);
    }

    /**
     * Method to get the primary record details
     * @param string $email
     * @param string $phoneNumber
     * return array
     */
    public function getPrimaryRecord($email, $phoneNumber) : array
    {
        $primaryRecordIds =  self::where(function ($query) use ($email, $phoneNumber) {
            $query->where(self::PHONENUMBER, $phoneNumber)
                ->orwhere(self::EMAIL, $email);
        })
        ->where(self::LINKEDPRECEDENCE, self::PRIMARY)
        ->select(self::ID, self::LINKEDID)
        ->orderBy(self::ID)
        ->get()
        ->toArray();
        
        $primaryContactIds = [];

        if (count($primaryRecordIds) == 0) {
            return [];
        } elseif (count($primaryRecordIds) == 2) {
            $primaryContactIds = array_unique(array_column($primaryRecordIds, "id"));
        } else {
            // when phone no is primary and email is secondary or vice-versa
            $recordIds = self::where(function ($query) use ($email, $phoneNumber) {
                $query->where(self::PHONENUMBER, $phoneNumber)
                    ->orwhere(self::EMAIL, $email);
            })
            ->whereNotNull(self::LINKEDID)
            ->select(self::LINKEDID)
            ->distinct()
            ->get()
            ->toArray();

            $primaryContactIds = array_column($recordIds, "linked_id");
            if (!in_array($primaryRecordIds[0]['id'], $primaryContactIds)) {
                array_push($primaryContactIds, $primaryRecordIds[0]['id']);
            }
        }

        return self::whereIn("id", $primaryContactIds)
            ->where(self::LINKEDPRECEDENCE, self::PRIMARY)
            ->select(
                self::ID,
                self::EMAIL,
                self::PHONENUMBER,
                self::LINKEDID,
            )
            ->orderBy(self::ID)
            ->get()
            ->toArray();
    }

    /**
     * Method to get the user identity
     * @param string $email
     * @param string $phoneNumber
     * @return array
     */
    public function getUserIdentity($email, $phoneNumber) : array
    {
        $isContactAlreadyExist = $this->isContactAlreadyExist($email, $phoneNumber);

        if (!$isContactAlreadyExist) {
            if ($phoneNumber == null || $email == null) {
                return [
                    "status" => false,
                    "error" => "Input Fields cannot be null"
                ];
            }
            $contactDetails = $this->createRecords($email, $phoneNumber, true);
            if ($contactDetails['status'] == false) {
                return [
                    "status" => false,
                    "error" => "Server error contact dev team."
                ];
            }

            return [
                "status" => true,
                "primaryContatctId" => $contactDetails['id'],
                "emails" => [$email],
                "phoneNumbers" => [$phoneNumber],
                "secondaryContactIds" => []
            ];
        }

        $primarycontactDetails = $this->getPrimaryRecord($email, $phoneNumber);
        if (count($primarycontactDetails) > 1) {
            // when both record are primary
            $primaryContactId = $primarycontactDetails[0]['id'];

            $idsToUpdate = [];
            foreach ($primarycontactDetails as $primarycontactDetail) {
                if ($primarycontactDetail['id'] != $primaryContactId) {
                    array_push($idsToUpdate, $primarycontactDetail['id']);
                }
            }
            
            $transactionStatus = DB::transaction(function() use ($idsToUpdate, $primaryContactId) {
                $valuesToUpdate[self::LINKEDID] = $primaryContactId;
                $updateStatus = $this->updateRecords(self::LINKEDID, $idsToUpdate, $valuesToUpdate);

                if (!$updateStatus) {
                    return false;
                }

                $valuesToUpdate[self::LINKEDPRECEDENCE] = self::SECONDARY;
                $updateStatus = $this->updateRecords(self::ID, $idsToUpdate, $valuesToUpdate);

                if (!$updateStatus) {
                    return false;
                }

                return true;
            });

            if ($transactionStatus == false) {
                return [
                    "status" => false,
                    "error" => "Server error contact dev team."
                ];
            }

            $secondaryContactDetails = $this->getSecondaryContactDetails($primaryContactId);

            return $this->formatContactDetails($email, $phoneNumber, $primarycontactDetails[0], $secondaryContactDetails);
        } elseif (count($primarycontactDetails) == 1) {
            // when only one primary contact is found
            $secondaryContactDetails = $this->getSecondaryContactDetails($primarycontactDetails[0]['id']);

            return $this->formatContactDetails($email, $phoneNumber, $primarycontactDetails[0], $secondaryContactDetails);
        } else {
            // both email and phone no are in secondary
            $secondaryContactDetails = $this->getSecondaryContactDetailsUsingEmailOrPhoneNo($email, $phoneNumber);

            $linkedIds = array_unique(array_column($secondaryContactDetails, "linked_id"));
            if (count($linkedIds) == 1) {
                // when both phone no and email linked to the same primary id
                $primaryContactId = $linkedIds[0];
                $primarycontactDetail = $this->getPrimaryRecordDetailUsingId($primaryContactId);
                
                // getting additional secondary contact details using id
                $additionalSecondarycontactDetails = $this->getSecondaryContactDetails($primaryContactId);

                if ($additionalSecondarycontactDetails) {
                    $secondaryContactDetails = array_merge(
                        $secondaryContactDetails,
                        $additionalSecondarycontactDetails
                    );
                }

                return $this->formatContactDetails($email, $phoneNumber, $primarycontactDetail, $secondaryContactDetails);
            } else {
                // when both phone no and email linked to the different primary id
                sort($linkedIds);
                // first linked id is primary
                $primaryContactId = $linkedIds[0];
                $primarycontactDetail = $this->getPrimaryRecordDetailUsingId($primaryContactId);
                
                $idsToUpdate = [];
                for ($i = 1; $i < count($linkedIds); $i++) {
                    array_push($idsToUpdate, $linkedIds[$i]);
                }

                $transactionStatus = DB::transaction(function() use ($idsToUpdate, $primaryContactId) {
                    $valuesToUpdate = [
                        self::LINKEDID => $primaryContactId,
                    ];
                    $updateStatus = $this->updateRecords(self::LINKEDID, $idsToUpdate, $valuesToUpdate);

                    if (!$updateStatus) {
                        return false;
                    }

                    $valuesToUpdate[self::LINKEDPRECEDENCE] = self::SECONDARY;
                    $updateStatus = $this->updateRecords(self::ID, $idsToUpdate, $valuesToUpdate);

                    if (!$updateStatus) {
                        return false;
                    }

                    return true;
                });

                if (!$transactionStatus) {
                    return false;
                }

                $additionalSecondaryContactDetails = $this->getSecondaryContactDetails($primaryContactId);
    
                if ($additionalSecondaryContactDetails) {
                    $secondaryContactDetails = array_merge(
                        $secondaryContactDetails,
                        $additionalSecondaryContactDetails
                    );
                }

                return $this->formatContactDetails($email, $phoneNumber, $primarycontactDetail, $secondaryContactDetails);
            }
        }
    }

    /**
     * Method to get the primary contact details
     * @param int $primaryContactIds
     * @return array
     */
    public function getPrimaryRecordDetailUsingId($primaryContactId) : array
    {
        $recordDetail =  self::where(self::ID, $primaryContactId)
        ->select(
            self::ID,
            self::EMAIL,
            self::PHONENUMBER,
            self::LINKEDID,
            self::LINKEDPRECEDENCE
        )
        ->first();

        return json_decode($recordDetail, true);
    }

    /**
     * Method to get the secondary contact details
     * @param int $primaryContactId
     * @return array
     */
    public function getSecondaryContactDetails($primaryContactId) : array
    {
        return self::where(self::LINKEDID, $primaryContactId)
            ->select(
                self::ID,
                self::EMAIL,
                self::PHONENUMBER,
                self::LINKEDID
            )
            ->get()
            ->toArray();
    }

    /**
     * Method to update primary row to secondary
     * @param string $columnName
     * @param array $idsToUpdate
     * @param array $valuesToUpdate
     * @return bool
     */
    public function updateRecords($columnName, $idsToUpdate, $valuesToUpdate) : bool
    {
        try {
            self::whereIn($columnName, $idsToUpdate)
                ->update($valuesToUpdate);
        } catch (\Error $err) {
            Log::error(
                "Failed to update linked id and linked precedence column value for the records : "
                . json_encode($idsToUpdate) . " . Error : " . $err->getMessage()
            );

            return false;
        }

        return true;
    }

    /**
     * Method to format the contact details
     * @param string $email
     * @param string $phoneNumber
     * @param array $primarycontactDetail
     * @param array $secondaryContactDetails
     * @return array
     */
    public function formatContactDetails(
        $email,
        $phoneNumber,
        $primarycontactDetail,
        $secondaryContactDetails
    ) : array {
        $emails = $phoneNumbers = $secondaryContactIds = [];
        array_push($emails, $primarycontactDetail['email']);
        array_push($phoneNumbers, $primarycontactDetail['phone_number']);
        foreach ($secondaryContactDetails as $secondarycontactDetail) {
            if (!in_array($secondarycontactDetail['id'], $secondaryContactIds)) {
                array_push($secondaryContactIds, $secondarycontactDetail['id']);
            }
            if (!in_array($secondarycontactDetail['phone_number'], $phoneNumbers)) {
                array_push($phoneNumbers, $secondarycontactDetail['phone_number']);
            }
            if (!in_array($secondarycontactDetail['email'], $emails)) {
                array_push($emails, $secondarycontactDetail['email']);
            }
        }

        if ((!in_array($phoneNumber, $phoneNumbers))
            && ($phoneNumber != null)
            ) {
            // when phone no not exist in the database but email does
            $newRecord = $this->createRecords($email, $phoneNumber, false, $primarycontactDetail['id']);
            if ($newRecord['status'] == false) {
                Log::error("Failed to add a secondary record phone number");

                return [
                    "status" => "False",
                    "error" => "Server error contact dev team."
                ];
            }
            array_push($phoneNumbers, $phoneNumber);
            array_push($secondaryContactIds, $newRecord['id']);
        }
        if (($email != null)
            && (!in_array($email, $emails))
        ) {
            // when email not exist in the database but phone no does
            $newRecord = $this->createRecords($email, $phoneNumber, false, $primarycontactDetail['id']);
            if ($newRecord['status'] == false) {
                Log::error("Failed to add a secondary record email");

                return [
                    "status" => "False",
                    "error" => "Server error contact dev team."
                ];
            }
            array_push($emails, $email);
            array_push($secondaryContactIds, $newRecord['id']);
        }

        sort($emails);
        sort($phoneNumbers);
        sort($secondaryContactIds);

        return [
            "primaryContatctId" => $primarycontactDetail['id'],
            "emails" => $emails,
            "phoneNumbers" => $phoneNumbers,
            "secondaryContactIds" => $secondaryContactIds
        ];
    }

    /**
     * Method to get the secondary contact details using phone number or email
     * @param string $email
     * @param string $phoneNumber
     * @return array
     */
    public function getSecondaryContactDetailsUsingEmailOrPhoneNo($email, $phoneNumber) : array
    {
        return self::where(function ($query) use ($phoneNumber, $email) {
            $query->where(self::PHONENUMBER, $phoneNumber)
                ->orwhere(self::EMAIL, $email);
        })
        ->select(
            self::ID,
            self::EMAIL,
            self::PHONENUMBER,
            self::LINKEDID,
            self::LINKEDPRECEDENCE
        )
        ->orderBy(self::ID)
        ->get()
        ->toArray();
    }
}
