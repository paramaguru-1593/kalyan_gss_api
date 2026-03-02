<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerLedgerCollection;
use App\Models\CustomerSchemeDetail;
use App\Models\SchemeEnrollment;
use Illuminate\Support\Facades\DB;

/**
 * Persists getCustomerLedgerReport API response into customer_scheme_details and customer_ledger_collections.
 * Upserts scheme detail by enrollment_no; replaces collections for that detail.
 */
class GetCustomerLedgerReportSyncService
{
    /**
     * Sync getCustomerLedgerReport response to DB.
     * Expects response shape: data.Response.totalamount, data.Response.personalDetails, data.Response.Collections.
     *
     * @param  array<string, mixed>|object  $response  Raw API response
     * @return CustomerSchemeDetail|null  Synced model or null when response has no valid data
     */
    public function syncFromResponse($response): ?CustomerSchemeDetail
    {
        $response = $this->toArray($response);
        $data = $response['data'] ?? null;
        $data = $this->toArray($data);
        $responseBlock = $data['Response'] ?? $data['response'] ?? null;
        $responseBlock = $this->toArray($responseBlock);

        $personalDetails = $responseBlock['personalDetails'] ?? $responseBlock['personaldetails'] ?? null;
        $personalDetails = $this->toArray($personalDetails);

        $enrollmentNo = (string) ($personalDetails['EnrollmentNo'] ?? '');
        if ($enrollmentNo === '') {
            return null;
        }

        return DB::transaction(function () use ($responseBlock, $personalDetails, $enrollmentNo) {
            $detail = $this->upsertSchemeDetail($enrollmentNo, $responseBlock, $personalDetails);
            $this->syncCollections($detail, $responseBlock['Collections'] ?? $responseBlock['collections'] ?? []);
            return $detail->fresh(['collections']);
        });
    }

    /**
     * Build API-shaped response from a CustomerSchemeDetail (personalDetails + Collections).
     *
     * @return array{ data: array{ Response: array{ totalamount: mixed, personalDetails: array, Collections: array } }, error: array }
     */
    public function buildResponseFromDetail(CustomerSchemeDetail $detail): array
    {
        $detail->load('collections');

        $totalamount = $detail->totalamount ?? $detail->paid_amount ?? 0;
        if ($totalamount !== null) {
            $totalamount = (float) $totalamount;
        }

        $personalDetails = [
            'FirstName' => $detail->first_name ?? '',
            'LastName' => $detail->last_name ?? '',
            'MobileNumber' => $detail->mobile_number ?? '',
            'Address1' => $detail->address1 ?? '',
            'Address2' => $detail->address2 ?? '',
            'Pincode' => $detail->pincode ?? '',
            'Address3' => $detail->address3 ?? '',
            'State' => $detail->state ?? '',
            'MyKalyanName' => $detail->my_kalyan_name ?? '',
            'Branch' => $detail->branch ?? '',
            'EnrollmentNo' => $detail->enrollment_no ?? '',
            'SchemeStatus' => $detail->scheme_status ?? '',
            'JoinDate' => $detail->join_date ? $detail->join_date->format('Y-m-d') : '',
            'MaturityDate' => $detail->maturity_date ? $detail->maturity_date->format('Y-m-d') : '',
            'SchemeType' => $detail->scheme_name ?? '',
            'NoOfInstallments' => $detail->no_of_installments,
            'EMI' => $detail->emi !== null ? (float) $detail->emi : null,
            'UserFirstName' => $detail->user_first_name ?? '',
            'UserLastName' => $detail->user_last_name ?? '',
            'Username' => $detail->username ?? '',
            'MaterialType' => $detail->material_type ?? '',
            'ClosureDate' => $detail->closure_date ? $detail->closure_date->format('Y-m-d') : null,
            'FeeAmount' => $detail->fee_amount !== null ? (float) $detail->fee_amount : null,
            'TotalAmount' => $detail->total_amount !== null ? (float) $detail->total_amount : null,
            'PaidAmount' => $detail->paid_amount !== null ? (int) $detail->paid_amount : null,
            'RemainingAmount' => $detail->remaining_amount !== null ? (float) $detail->remaining_amount : null,
            'IDProofName' => $detail->id_proof_name ?? '',
            'Beneficiary' => $detail->beneficiary ?? '',
            'InstoreUserID' => $detail->instore_user_id,
            'InstoreUserName' => $detail->instore_user_name,
            'NomineeFirstName' => $detail->nominee_first_name ?? '',
            'NomineeLastName' => $detail->nominee_last_name ?? '',
            'NomineeRelationship' => $detail->nominee_relationship ?? '',
            'NomineeMobileNumber' => $detail->nominee_mobile_number ?? '',
            'NomineeAddress' => $detail->nominee_address ?? '',
            'NomineeEmailAddress' => $detail->nominee_email_address ?? '',
            'SchemeID' => $detail->scheme_id,
            'IDProofNumber' => $detail->id_proof_number ?? '',
            'IDProofType' => $detail->id_proof_type ?? '',
            'SchemeEfficientType' => $detail->scheme_efficient_type ?? '',
            'ReasonForInEfficient' => $detail->reason_for_in_efficient ?? '',
            'CustomerID' => $detail->customer_id_external ?? $detail->customer?->customerId,
            'DateOfBirth' => $detail->date_of_birth ? $detail->date_of_birth->format('Y-m-d') : '',
            'Gender' => $detail->gender ?? '',
            'emailAddress' => $detail->email_address ?? '',
            'SchemeName' => $detail->scheme_name ?? '',
            'SIONCC' => (bool) $detail->sioncc,
            'TransactionId' => $detail->transaction_id ?? '',
            'Emandate' => (bool) $detail->emandate,
            'DebitDate' => $detail->debit_date ?? '',
        ];

        $collections = $detail->collections->map(function (CustomerLedgerCollection $c) {
            $dateStr = $c->collection_date ? $c->collection_date->format('Y-F-j') : null; // e.g. 2024-December-12
            return [
                'ReferenceNo' => $c->reference_no !== null && is_numeric($c->reference_no) ? (int) $c->reference_no : $c->reference_no,
                'MOP' => $c->mop,
                'Remarks' => $c->remarks,
                'Date' => $dateStr,
                'Amount' => $c->amount !== null ? (int) $c->amount : null,
                'IssuedDate' => $c->issued_date ? $c->issued_date->format('Y-m-d') : null,
                'ChequeNumber' => $c->cheque_number,
                'EMIMonth' => $c->emi_month,
                'PaymentStatus' => $c->payment_status,
                'goldrate' => $c->gold_rate,
                'goldweight' => $c->gold_weight,
            ];
        })->values()->all();

        return [
            'data' => [
                'Response' => [
                    'totalamount' => $totalamount,
                    'personalDetails' => $personalDetails,
                    'Collections' => $collections,
                ],
            ],
            'error' => [
                'status' => 200,
                'message' => 'success',
                'description' => '',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $responseBlock  data.Response
     * @param  array<string, mixed>  $personalDetails  personalDetails map
     */
    private function upsertSchemeDetail(string $enrollmentNo, array $responseBlock, array $personalDetails): CustomerSchemeDetail
    {
        $totalamount = $responseBlock['totalamount'] ?? null;
        if ($totalamount !== null) {
            $totalamount = (float) $totalamount;
        }

        $customerIdExternal = isset($personalDetails['CustomerID']) ? (int) $personalDetails['CustomerID'] : null;
        $customerId = null;
        $schemeEnrollmentId = null;
        if ($customerIdExternal !== null) {
            $customer = Customer::where('customerId', $customerIdExternal)->first();
            if ($customer) {
                $customerId = $customer->id;
            }
        }
        $enrollment = SchemeEnrollment::where('enrollment_id', $enrollmentNo)->first();
        if ($enrollment) {
            $schemeEnrollmentId = $enrollment->id;
        }

        $joinDate = $this->parseDate($personalDetails['JoinDate'] ?? null);
        $maturityDate = $this->parseDate($personalDetails['MaturityDate'] ?? null);
        $closureDate = $this->parseDate($personalDetails['ClosureDate'] ?? null);
        $dateOfBirth = $this->parseDate($personalDetails['DateOfBirth'] ?? null);

        $attributes = [
            'customer_id' => $customerId,
            'scheme_enrollment_id' => $schemeEnrollmentId,
            'customer_id_external' => $customerIdExternal,
            'scheme_id' => isset($personalDetails['SchemeID']) ? (int) $personalDetails['SchemeID'] : null,
            'scheme_name' => $personalDetails['SchemeName'] ?? $personalDetails['SchemeType'] ?? null,
            'scheme_status' => $personalDetails['SchemeStatus'] ?? null,
            'join_date' => $joinDate,
            'maturity_date' => $maturityDate,
            'closure_date' => $closureDate,
            'no_of_installments' => isset($personalDetails['NoOfInstallments']) ? (int) $personalDetails['NoOfInstallments'] : null,
            'emi' => $this->floatOrNull($personalDetails['EMI'] ?? null),
            'total_amount' => $this->floatOrNull($personalDetails['TotalAmount'] ?? null),
            'paid_amount' => $this->floatOrNull($personalDetails['PaidAmount'] ?? null),
            'remaining_amount' => $this->floatOrNull($personalDetails['RemainingAmount'] ?? null),
            'fee_amount' => $this->floatOrNull($personalDetails['FeeAmount'] ?? null),
            'totalamount' => $totalamount,
            'first_name' => $personalDetails['FirstName'] ?? null,
            'last_name' => $personalDetails['LastName'] ?? null,
            'mobile_number' => $personalDetails['MobileNumber'] ?? null,
            'email_address' => $personalDetails['emailAddress'] ?? null,
            'address1' => $personalDetails['Address1'] ?? null,
            'address2' => $personalDetails['Address2'] ?? null,
            'address3' => $personalDetails['Address3'] ?? null,
            'pincode' => $personalDetails['Pincode'] ?? null,
            'state' => $personalDetails['State'] ?? null,
            'branch' => $personalDetails['Branch'] ?? null,
            'my_kalyan_name' => $personalDetails['MyKalyanName'] ?? null,
            'material_type' => $personalDetails['MaterialType'] ?? null,
            'user_first_name' => $personalDetails['UserFirstName'] ?? null,
            'user_last_name' => $personalDetails['UserLastName'] ?? null,
            'username' => $personalDetails['Username'] ?? null,
            'instore_user_id' => $personalDetails['InstoreUserID'] ?? null,
            'instore_user_name' => $personalDetails['InstoreUserName'] ?? null,
            'id_proof_name' => $personalDetails['IDProofName'] ?? null,
            'id_proof_number' => $personalDetails['IDProofNumber'] ?? null,
            'id_proof_type' => $personalDetails['IDProofType'] ?? null,
            'beneficiary' => $personalDetails['Beneficiary'] ?? null,
            'date_of_birth' => $dateOfBirth,
            'gender' => $personalDetails['Gender'] ?? null,
            'nominee_first_name' => $personalDetails['NomineeFirstName'] ?? null,
            'nominee_last_name' => $personalDetails['NomineeLastName'] ?? null,
            'nominee_relationship' => $personalDetails['NomineeRelationship'] ?? null,
            'nominee_mobile_number' => $personalDetails['NomineeMobileNumber'] ?? null,
            'nominee_address' => $personalDetails['NomineeAddress'] ?? null,
            'nominee_email_address' => $personalDetails['NomineeEmailAddress'] ?? null,
            'scheme_efficient_type' => $personalDetails['SchemeEfficientType'] ?? null,
            'reason_for_in_efficient' => $personalDetails['ReasonForInEfficient'] ?? null,
            'sioncc' => (bool) ($personalDetails['SIONCC'] ?? false),
            'transaction_id' => $personalDetails['TransactionId'] ?? null,
            'emandate' => (bool) ($personalDetails['Emandate'] ?? false),
            'debit_date' => $personalDetails['DebitDate'] ?? null,
        ];

        return CustomerSchemeDetail::updateOrCreate(
            ['enrollment_no' => $enrollmentNo],
            $attributes
        );
    }

    /**
     * @param  array<int, mixed>  $collections  Response.Collections
     */
    private function syncCollections(CustomerSchemeDetail $detail, array $collections): void
    {
        $detail->collections()->delete();

        foreach ($collections as $item) {
            $item = $this->toArray($item);
            $refNo = $item['ReferenceNo'] ?? null;
            if ($refNo !== null && $refNo !== '') {
                $refNo = (string) $refNo;
            } else {
                $refNo = null;
            }

            $dateRaw = $item['Date'] ?? null;
            $collectionDate = null;
            if ($dateRaw !== null && $dateRaw !== '') {
                $collectionDate = $this->parseDateMonthName((string) $dateRaw);
            }

            $issuedDate = $this->parseDate($item['IssuedDate'] ?? null);

            CustomerLedgerCollection::create([
                'customer_scheme_detail_id' => $detail->id,
                'reference_no' => $refNo,
                'mop' => $item['MOP'] ?? null,
                'remarks' => $item['Remarks'] ?? null,
                'collection_date' => $collectionDate,
                'amount' => $this->floatOrNull($item['Amount'] ?? null),
                'issued_date' => $issuedDate,
                'cheque_number' => $item['ChequeNumber'] ?? null,
                'emi_month' => $item['EMIMonth'] ?? null,
                'payment_status' => $item['PaymentStatus'] ?? null,
                'gold_rate' => $this->floatOrNull($item['goldrate'] ?? null),
                'gold_weight' => $this->floatOrNull($item['goldweight'] ?? null),
            ]);
        }
    }

    /**
     * @param  mixed  $value
     * @return array<string, mixed>
     */
    private function toArray($value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_object($value)) {
            return json_decode(json_encode($value), true) ?? [];
        }
        return [];
    }

    /**
     * @param  mixed  $value
     * @return string|null  Y-m-d
     */
    private function parseDate($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        $str = (string) $value;
        $time = strtotime($str);
        return $time ? date('Y-m-d', $time) : null;
    }

    /**
     * Parse date string like "2024-December-12" to Y-m-d.
     */
    private function parseDateMonthName(string $value): ?string
    {
        $time = strtotime($value);
        return $time ? date('Y-m-d', $time) : null;
    }

    /**
     * @param  mixed  $value
     */
    private function floatOrNull($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        return (float) $value;
    }
}
