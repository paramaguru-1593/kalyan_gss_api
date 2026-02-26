<?php

namespace App\Services;

use App\Models\Scheme;
use Illuminate\Support\Collection;

/**
 * Syncs scheme data from third-party API responses into the schemes table.
 * Inserts only when a scheme with the same (store_id, scheme_name, no_of_installment) does not exist.
 */
class SchemeSyncService
{
    /** Default store_id when not provided (e.g. from getSchemesByMobileNumber response). */
    public const DEFAULT_STORE_ID = 3;

    /**
     * Extract enrollment list from getSchemesByMobileNumber response.
     * Handles: data.Response.data.enrollmentList, data.enrollmentList, enrollmentList, or data as array of enrollments.
     *
     * @param  array<string, mixed>|object  $response
     * @return array<int, array<string, mixed>>
     */
    public function extractEnrollmentList($response): array
    {
        $response = $this->toArray($response);
        if ($response === []) {
            return [];
        }

        $data = $response['data'] ?? null;
        $data = $this->toArray($data);

        // data.Response.data.enrollmentList (actual getSchemesByMobileNumber response structure)
        $responseData = $data['Response']['data'] ?? $data['response']['data'] ?? null;
        $responseData = $this->toArray($responseData);
        $list = $responseData['enrollmentList'] ?? $responseData['enrollmentlist'] ?? null;
        if (is_array($list) && $this->isEnrollmentList($list)) {
            return $list;
        }

        // data.enrollmentList or data.enrollmentlist (case variation)
        $list = $data['enrollmentList'] ?? $data['enrollmentlist'] ?? null;
        if (is_array($list) && $this->isEnrollmentList($list)) {
            return $list;
        }

        // Top-level enrollmentList
        $list = $response['enrollmentList'] ?? $response['enrollmentlist'] ?? null;
        if (is_array($list) && $this->isEnrollmentList($list)) {
            return $list;
        }

        // data is the array of enrollments (numeric keys, each item has PlanType/SchemeID)
        if (is_array($data) && $this->isEnrollmentList($data)) {
            return $data;
        }

        // Single enrollment at top level
        if (isset($response['PlanType']) || isset($response['SchemeID'])) {
            return [$response];
        }

        return [];
    }

    /**
     * @param  mixed  $list
     */
    private function isEnrollmentList($list): bool
    {
        if (! is_array($list) || $list === []) {
            return false;
        }
        $first = reset($list);
        $first = $this->toArray($first);
        return isset($first['PlanType']) || isset($first['SchemeID']) || isset($first['NoMonths']);
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
     * Sync a single scheme from storebasedscheme_data response.
     * Response item: id, scheme_name, no_of_installment, monthly_emi_per_month (or min/max).
     *
     * @param  int  $storeId
     * @param  array{id?: int, scheme_name?: string, no_of_installment?: int, monthly_emi_per_month?: float|int|null, min_installment_amount?: float|int|null, max_installment_amount?: float|int|null, weight_allocation?: bool}  $row
     * @return Scheme
     */
    public function syncFromStoreBasedRow(int $storeId, array $row): Scheme
    {
        $schemeName = (string) ($row['scheme_name'] ?? '');
        $noOfInstallment = (int) ($row['no_of_installment'] ?? 0);

        if ($schemeName === '' || $noOfInstallment <= 0) {
            return Scheme::create([
                'store_id' => $storeId,
                'scheme_name' => $schemeName ?: 'Unknown',
                'no_of_installment' => max(1, $noOfInstallment),
                'min_installment_amount' => $this->nullableDecimal($row['monthly_emi_per_month'] ?? $row['min_installment_amount'] ?? null),
                'max_installment_amount' => $this->nullableDecimal($row['max_installment_amount'] ?? $row['max_instamment_amount'] ?? null),
                'weight_allocation' => (bool) ($row['weight_allocation'] ?? true),
            ]);
        }

        return Scheme::firstOrCreate(
            [
                'store_id' => $storeId,
                'scheme_name' => $schemeName,
                'no_of_installment' => $noOfInstallment,
            ],
            [
                'min_installment_amount' => $this->nullableDecimal($row['monthly_emi_per_month'] ?? $row['min_installment_amount'] ?? null),
                'max_installment_amount' => $this->nullableDecimal($row['max_installment_amount'] ?? $row['max_instamment_amount'] ?? null),
                'weight_allocation' => (bool) ($row['weight_allocation'] ?? true),
            ]
        );
    }

    /**
     * Sync multiple schemes from storebasedscheme_data response.
     * Accepts response with top-level array or { data: [...] }.
     *
     * @param  int  $storeId
     * @param  array<string, mixed>  $response  Third-party storebasedscheme_data response
     * @return Collection<int, Scheme>
     */
    public function syncFromStoreBasedResponse(int $storeId, array $response): Collection
    {
        $items = $response['data'] ?? $response;
        if (! is_array($items)) {
            $items = [];
        }
        if (isset($items['id']) || isset($items['scheme_name'])) {
            $items = [$items];
        }

        $schemes = collect();
        foreach ($items as $row) {
            if (is_array($row)) {
                $schemes->push($this->syncFromStoreBasedRow($storeId, $row));
            }
        }

        return $schemes;
    }

    /**
     * Sync schemes from getSchemesByMobileNumber response.
     * Response contains enrollmentList (array of enrollments with SchemeID, PlanType, NoMonths, EMIAmount, etc.).
     * Extracts scheme details from each enrollment and stores unique schemes in the schemes table.
     *
     * @param  int  $storeId
     * @param  array<string, mixed>|object  $response  Third-party getSchemesByMobileNumber response
     * @return Collection<int, Scheme>
     */
    public function syncFromMobileNumberResponse(int $storeId, $response): Collection
    {
        $items = $this->extractEnrollmentList($response);

        $schemes = collect();
        foreach ($items as $row) {
            $row = $this->toArray($row);
            if ($row === []) {
                continue;
            }
            $planType = (string) ($row['PlanType'] ?? $row['SchemeName'] ?? '');
            $noMonths = (int) ($row['NoMonths'] ?? 0);
            $emiAmount = $this->nullableDecimal($row['EMIAmount'] ?? null);
            if ($planType === '' && $noMonths <= 0) {
                continue;
            }
            if ($noMonths <= 0) {
                $noMonths = 1;
            }

            $schemes->push(Scheme::firstOrCreate(
                [
                    'store_id' => $storeId,
                    'scheme_name' => $planType,
                    'no_of_installment' => $noMonths,
                ],
                [
                    'min_installment_amount' => $emiAmount,
                    'weight_allocation' => true,
                ]
            ));
        }

        return $schemes;
    }

    /**
     * @param  mixed  $value
     * @return float|null
     */
    private function nullableDecimal($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        return (float) $value;
    }
}
