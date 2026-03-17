<?php

namespace App\Services;

use Carbon\Carbon;

class BillDeskResponse
{
    public $merchantID;
    public $customerID;
    public $txnReferenceNo;
    public $bankReferenceNo;
    public $txnAmount;
    public $bankID;
    public $bankMerchantID;
    public $txnType;
    public $currencyName;
    public $itemCode;
    public $securityType;
    public $securityID;
    public $securityPassword;
    public $txnDate;
    public $authStatus;
    public $settlementType;
    public $additionalInfo1;
    public $additionalInfo2;
    public $additionalInfo3;
    public $additionalInfo4;
    public $additionalInfo5;
    public $additionalInfo6;
    public $additionalInfo7;
    public $errorStatus;
    public $errorDescription;
    public $checkSum;

    public $txnStatus;
    public $txnStatusReason;

    private static $authStatusMap = [
        "0300" => "Success",
        "0399" => "Invalid Authentication at Bank",
        "NA"   => "Invalid Input in the Request Message",
        "0002" => "BillDesk is waiting for Response from Bank",
        "0001" => "Error at BillDesk Cancel Transaction",
    ];

    public function __construct($msg)
    {
        $response = explode('|', $msg);

        $this->merchantID = $response[0] ?? null;
        $this->customerID = $response[1] ?? null;
        $this->txnReferenceNo = $response[2] ?? null;
        $this->bankReferenceNo = $response[3] ?? null;
        $this->txnAmount = $response[4] ?? null;
        $this->bankID = $response[5] ?? null;
        $this->bankMerchantID = $response[6] ?? null;
        $this->txnType = $response[7] ?? null;
        $this->currencyName = $response[8] ?? null;
        $this->itemCode = $response[9] ?? null;
        $this->securityType = $response[10] ?? null;
        $this->securityID = $response[11] ?? null;
        $this->securityPassword = $response[12] ?? null;

        // Convert date safely
        try {
            $this->txnDate = Carbon::createFromFormat('d-m-Y H:i:s', $response[13]);
        } catch (\Exception $e) {
            $this->txnDate = null;
        }

        $this->authStatus = $response[14] ?? null;
        $this->settlementType = $response[15] ?? null;

        $this->additionalInfo1 = $response[16] ?? null;
        $this->additionalInfo2 = $response[17] ?? null;
        $this->additionalInfo3 = $response[18] ?? null;
        $this->additionalInfo4 = $response[19] ?? null;
        $this->additionalInfo5 = $response[20] ?? null;
        $this->additionalInfo6 = $response[21] ?? null;
        $this->additionalInfo7 = $response[22] ?? null;

        $this->errorStatus = $response[23] ?? null;
        $this->errorDescription = $response[24] ?? null;
        $this->checkSum = $response[25] ?? null;

        // Map status
        $this->txnStatusReason =
            self::$authStatusMap[$this->authStatus] ?? "Unknown";

        $this->txnStatus =
            $this->authStatus === "0300"
                ? "Successful Transaction"
                : "Cancel Transaction";
    }
}