<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\SchemeEnrollment;
use Illuminate\Support\Facades\DB;

/**
 * Persists getSchemesByMobileNumber API response into customers and scheme_enrollments.
 * - Upserts customer by mobile number; avoids duplicate customer records.
 * - Upserts each enrollment by enrollment_id; updates total_enrollments on customer.
 */
class GetSchemesByMobileNumberSyncService
{
    public function __construct(
        private readonly SchemeSyncService $schemeSync
    ) {
    }

    /**
     * Sync full getSchemesByMobileNumber response: customer + enrollments, then refresh total_enrollments.
     *
     * @param  array<string, mixed>|object  $response  Raw API response (data.Response.data with profile.enrollmentList)
     * @return array{customer: Customer, enrollments_count: int}|null  Null when response has no customer data
     */
    public function syncFromResponse($response): ?array
    {
        $response = $this->toArray($response);
        $data = $response['data'] ?? null;
        $data = $this->toArray($data);
        $responseData = $data['Response']['data'] ?? $data['response']['data'] ?? null;
        $responseData = $this->toArray($responseData);

        if (empty($responseData)) {
            return null;
        }

        $customer = $this->upsertCustomerFromResponseData($responseData);
        if (! $customer) {
            return null;
        }

        $enrollmentList = $this->schemeSync->extractEnrollmentList($response);
        $enrollmentsCount = 0;

        foreach ($enrollmentList as $item) {
            $item = $this->toArray($item);
            $enrollment = $this->upsertEnrollmentFromRow($customer->id, $item);
            if ($enrollment) {
                $enrollmentsCount++;
            }
        }

        $customer->refreshTotalEnrollments();

        return [
            'customer' => $customer->fresh(),
            'enrollments_count' => $enrollmentsCount,
        ];
    }

    /**
     * Save or update customer by mobile number. Uses mobile_no for uniqueness (API: MobileNumber).
     *
     * @param  array<string, mixed>  $attributes  Keys: mobile_no (required), first_name, last_name, date_of_birth, gender, address, nominee_name, relation_of_nominee, nominee_dob, nominee_mobile_number, etc.
     */
    public function upsertCustomerByMobileNumber(string $mobileNumber, array $attributes): Customer
    {
        $mobile = $this->normalizeMobile($mobileNumber);
        $attributes['mobile_no'] = $mobile;

        return Customer::updateOrCreate(
            ['mobile_no' => $mobile],
            $attributes
        );
    }

    /**
     * Save or update enrollment by enrollment_id. Links to customer_id.
     *
     * @param  array<string, mixed>  $attributes  Keys: scheme_id, scheme_name, enrollment_date, maturity_date, installment_amount, paid_amount, pending_amount, status, etc.
     */
    public function upsertEnrollmentByEnrollmentId(int $customerId, string $enrollmentId, array $attributes): SchemeEnrollment
    {
        $attributes['customer_id'] = $customerId;
        $attributes['enrollment_id'] = (string) $enrollmentId;

        return SchemeEnrollment::updateOrCreate(
            ['enrollment_id' => $attributes['enrollment_id']],
            $attributes
        );
    }

    /**
     * Recompute and persist total_enrollments for a customer (e.g. after bulk enrollment changes).
     */
    public function refreshCustomerTotalEnrollments(Customer $customer): int
    {
        return $customer->refreshTotalEnrollments();
    }

    /**
     * @param  array<string, mixed>  $responseData  data.Response.data (customerId, profile)
     */
    private function upsertCustomerFromResponseData(array $responseData): ?Customer
    {
        $profile = $responseData['profile'] ?? [];
        $profile = $this->toArray($profile);
        $personal = $profile['personalDetails'] ?? $profile['personaldetails'] ?? [];
        $personal = $this->toArray($personal);

        $mobile = $this->normalizeMobile((string) ($personal['MobileNumber'] ?? ''));
        if ($mobile === '') {
            return null;
        }

        $currentAddress = $profile['currentAddress'] ?? $profile['currentaddress'] ?? [];
        $currentAddress = $this->toArray($currentAddress);
        $address = $this->formatAddress($currentAddress);

        $customerId = $responseData['customerId'] ?? null;
        if (is_numeric($customerId)) {
            $customerId = (int) $customerId;
        } else {
            $customerId = null;
        }

        $customer = Customer::updateOrCreate(
            ['mobile_no' => $mobile],
            [
                'customerId' => $customerId,
                'first_name' => $personal['FirstName'] ?? null,
                'last_name' => $personal['LastName'] ?? null,
                'email' => $personal['EmailAddress'] ?? null,
                'address' => $address ?: null,
                'current_house_no' => $currentAddress['street1'] ?? null,
                'current_street' => $currentAddress['street2'] ?? null,
                'current_city' => $currentAddress['city'] ?? null,
                'current_state' => $currentAddress['state'] ?? null,
                'current_pincode' => $currentAddress['pinCode'] ?? $currentAddress['postOffice'] ?? null,
            ]
        );

        return $customer;
    }

    /**
     * @param  array<string, mixed>  $row  Single item from enrollmentList (EnrollmentID, SchemeID, PlanType, JoinDate, EndDate, EMIAmount, TotalPaidAmount, etc.)
     */
    private function upsertEnrollmentFromRow(int $customerId, array $row): ?SchemeEnrollment
    {
        $enrollmentId = $row['EnrollmentID'] ?? $row['EnrollmentId'] ?? null;
        if ($enrollmentId === null || $enrollmentId === '') {
            return null;
        }
        $enrollmentId = (string) $enrollmentId;

        $joinDate = $row['JoinDate'] ?? null;
        $endDate = $row['EndDate'] ?? null;
        $totalPaid = isset($row['TotalPaidAmount']) ? (float) $row['TotalPaidAmount'] : 0;
        $finalRedeemable = isset($row['FinalRedeemableAmount']) ? (float) $row['FinalRedeemableAmount'] : null;
        $pending = $finalRedeemable !== null ? $finalRedeemable - $totalPaid : null;

        return SchemeEnrollment::updateOrCreate(
            ['enrollment_id' => $enrollmentId],
            [
                'customer_id' => $customerId,
                'scheme_id' => isset($row['SchemeID']) ? (int) $row['SchemeID'] : null,
                'scheme_name' => $row['PlanType'] ?? $row['SchemeName'] ?? null,
                'enrollment_date' => $this->parseDate($joinDate),
                'maturity_date' => $this->parseDate($endDate),
                'installment_amount' => $row['EMIAmount'] ?? 0,
                'paid_amount' => $totalPaid,
                'pending_amount' => $pending,
                'status' => $row['Status'] ?? null,
            ]
        );
    }

    private function normalizeMobile(string $value): string
    {
        return trim(preg_replace('/\s+/', '', $value));
    }

    /**
     * @param  array<string, mixed>  $currentAddress
     */
    private function formatAddress(array $currentAddress): string
    {
        $parts = array_filter([
            $currentAddress['street1'] ?? '',
            $currentAddress['street2'] ?? '',
            $currentAddress['city'] ?? '',
            $currentAddress['state'] ?? '',
            $currentAddress['pinCode'] ?? $currentAddress['postOffice'] ?? '',
        ]);
        return implode(', ', $parts);
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
     * @return string|null  Y-m-d or null
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
}
