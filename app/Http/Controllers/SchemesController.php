<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SchemesController extends Controller
{
    /**
     * Get scheme information by mobile number.
     * GET /api/externals/getSchemesByMobileNumber
     * Query: MobileNumber (required). Header: access_token (optional, no validation).
     */
    public function getSchemesByMobileNumber(Request $request): JsonResponse
    {
        $request->validate([
            'MobileNumber' => 'required|string|max:50',
        ], [
            'MobileNumber.required' => 'MobileNumber is required',
        ]);

        $mobileNumber = $request->query('MobileNumber');

        // TODO: Replace with actual lookup (DB/external service). For now returns stub.
        $data = $this->findSchemesByMobileNumber($mobileNumber);

        if ($data === null) {
            return response()->json([
                'data' => (object) [],
                'error' => [
                    'status' => 10000,
                    'message' => 'No Data Found',
                    'description' => 'Failed',
                ],
            ]);
        }

        return response()->json([
            'data' => [
                'Response' => [
                    'data' => $data,
                ],
            ],
            'error' => [
                'status' => 200,
                'message' => 'success',
                'description' => '',
            ],
        ]);
    }

    /**
     * Get scheme information by account number (EnrollmentID).
     * GET /api/Enrollment_tbs/getAccountInformation
     * Query: EnrollmentID (required). Header: access_token (optional, no validation).
     */
    public function getAccountInformation(Request $request): JsonResponse
    {
        $request->validate([
            'EnrollmentID' => 'required|string|max:50',
        ], [
            'EnrollmentID.required' => 'EnrollmentID is required',
        ]);

        $enrollmentId = $request->query('EnrollmentID');
        $accountData = $this->findAccountByEnrollmentId($enrollmentId);

        if ($accountData === null) {
            return response()->json([
                'data' => [(object) []],
                'error' => [
                    'status' => 4002,
                    'message' => "Account doesn't exist",
                    'description' => "Account doesn't exist",
                ],
            ]);
        }

        return response()->json([
            'data' => [$accountData],
            'error' => [
                'status' => 200,
                'message' => 'success',
                'description' => '',
            ],
        ]);
    }

    /**
     * Get Scheme List – available schemes with installments and min/max EMI. Default store_id is 3.
     * POST /api/storebasedscheme_data
     */
    public function storeBasedSchemeData(Request $request): JsonResponse
    {
        $storeId = $request->input('store_id', 3);
        if (!is_numeric($storeId) || (int) $storeId <= 0) {
            return response()->json([
                'error' => [
                    'status' => 400,
                    'message' => 'Invalid Store ID !!',
                ],
            ], 400);
        }

        $storeId = (int) $storeId;
        $schemes = $this->findStoreBasedSchemes($storeId);

        if ($schemes === null) {
            return response()->json([
                'error' => [
                    'status' => 400,
                    'message' => 'Invalid Store ID !!',
                ],
            ], 400);
        }

        return response()->json([
            'data' => $schemes,
        ]);
    }

    /**
     * Get Customer Ledger – transaction history and financial info by enrollment number.
     * GET /api/externals/getCustomerLedgerReport
     * Query: EnrollmentNo (required). Header: access_token (optional, no validation).
     */
    public function getCustomerLedgerReport(Request $request): JsonResponse
    {
        $request->validate([
            'EnrollmentNo' => 'required|string|max:50',
        ], [
            'EnrollmentNo.required' => 'EnrollmentNo is required',
        ]);

        $enrollmentNo = $request->query('EnrollmentNo');
        $result = $this->findCustomerLedger($enrollmentNo);

        if ($result === null) {
            return response()->json([
                'data' => [
                    'Response' => [
                        'data' => [],
                        'error' => [
                            [
                                'statusCode' => 500,
                                'statusMessage' => 'Invalid Enrollment Number',
                            ],
                        ],
                    ],
                ],
                'error' => [
                    'status' => 200,
                    'message' => 'success',
                    'description' => '',
                ],
            ]);
        }

        return response()->json([
            'data' => [
                'Response' => $result,
            ],
            'error' => [
                'status' => 200,
                'message' => 'success',
                'description' => '',
            ],
        ]);
    }

    /**
     * Find schemes by store_id. Return null for invalid store.
     */
    private function findStoreBasedSchemes(int $storeId): ?array
    {
        // TODO: Replace with DB or external service lookup.
        return [
            [
                'id' => 1,
                'scheme_name' => 'Scheme Name1',
                'no_of_installment' => 12,
                'min_installment_amount' => 2000,
                'max_instamment_amount' => 5000,
                'weight_allocation' => true,
            ],
            [
                'id' => 2,
                'scheme_name' => 'scheme name2',
                'no_of_installment' => 10,
                'min_installment_amount' => null,
                'max_instamment_amount' => null,
                'weight_allocation' => true,
            ],
        ];
    }

    /**
     * Find customer ledger by EnrollmentNo. Return null for invalid enrollment.
     */
    private function findCustomerLedger(string $enrollmentNo): ?array
    {
        $enrollmentNo = trim($enrollmentNo);
        if ($enrollmentNo === '') {
            return null;
        }

        // TODO: Replace with DB or external service lookup.
        return [
            'totalamount' => 2000,
            'personalDetails' => [
                'FirstName' => 'User',
                'LastName' => 'G',
                'MobileNumber' => '9994795321',
                'Address1' => 'NO. 172',
                'Address2' => 'NORTH VILLAGE STREET',
                'Pincode' => '',
                'Address3' => '',
                'State' => 'Kerala',
                'MyKalyanName' => 'Kerala',
                'Branch' => 'Kerala',
                'EnrollmentNo' => $enrollmentNo,
                'SchemeStatus' => 'Open',
                'JoinDate' => '2024-12-12',
                'MaturityDate' => '2025-09-12',
                'SchemeType' => 'Akshaya Priority Scheme',
                'NoOfInstallments' => 9,
                'EMI' => 1000.0,
                'UserFirstName' => 'Rengaraj',
                'UserLastName' => 'G',
                'Username' => '6053',
                'MaterialType' => 'GOLD',
                'ClosureDate' => null,
                'FeeAmount' => 0.0,
                'TotalAmount' => 9000.0,
                'PaidAmount' => 2000,
                'RemainingAmount' => 7000.0,
                'IDProofName' => 'customer/proof/2024/12/12/testimage.jpeg',
                'Beneficiary' => 'RENGARAJ G',
                'InstoreUserID' => null,
                'InstoreUserName' => null,
                'NomineeFirstName' => 'G',
                'NomineeLastName' => '',
                'NomineeRelationship' => 'SON',
                'NomineeMobileNumber' => '',
                'NomineeAddress' => '',
                'NomineeEmailAddress' => '',
                'SchemeID' => 1001,
                'IDProofNumber' => '123456778',
                'IDProofType' => 'PASSPORT',
                'SchemeEfficientType' => 'InEfficient',
                'ReasonForInEfficient' => 'Not all the installments have been paid',
                'CustomerID' => 58691733978856,
                'DateOfBirth' => '',
                'Gender' => 'male',
                'emailAddress' => 'renarv88@gmail.com',
                'SchemeName' => 'Akshaya Priority Scheme',
                'SIONCC' => false,
                'TransactionId' => '',
                'Emandate' => false,
                'DebitDate' => '',
            ],
            'Collections' => [
                [
                    'ReferenceNo' => 110000000017,
                    'MOP' => 'CARD',
                    'Remarks' => 'NA',
                    'Date' => '2024-December-12',
                    'Amount' => 1000,
                    'IssuedDate' => null,
                    'ChequeNumber' => null,
                    'EMIMonth' => 'December 2024',
                    'PaymentStatus' => 'Completed',
                    'goldrate' => null,
                    'goldweight' => null,
                ],
            ],
        ];
    }

    /**
     * Find account/scheme data by EnrollmentID. Return null when account doesn't exist.
     */
    private function findAccountByEnrollmentId(string $enrollmentId): ?array
    {
        $enrollmentId = trim($enrollmentId);
        if ($enrollmentId === '') {
            return null;
        }

        // TODO: Replace with DB or external service lookup.
        // Stub: return sample structure for any non-empty EnrollmentID.
        return [
            'CustomerID' => 58691733978856,
            'FirstName' => 'User',
            'LastName' => 'G',
            'MobileNumber' => '9994795321',
            'JoinDate' => '2024-12-12 00:09:28',
            'EMIAmount' => 1000.0,
            'TotalInstallmentAmount' => 9000.0,
            'NoOfPaidInstallment' => 2,
            'RemainingAmount' => 7000.0,
            'EnrollmentID' => $enrollmentId,
            'AmountPaid' => 2000.0,
            'paymentAccepted' => true,
            'paymentAcceptedMonth' => 'Next Month',
            'Status' => 'Open',
            'SchemeName' => 'Akshaya Priority Scheme',
            'SchemeEfficientType' => 'InEfficient',
            'SchemeID' => 1001,
            'NoOfInstallments' => 9,
            'ReasonForInEfficient' => 'Not all the installments have been paid',
            'SIONCC' => false,
            'DebitDate' => '',
            'TransactionId' => '',
            'Emandate' => false,
            'IDProofStatus' => 'Not Verified',
        ];
    }

    /**
     * Find scheme/customer data by mobile number. Return null when no data found.
     */
    private function findSchemesByMobileNumber(string $mobileNumber): ?array
    {
        // TODO: Replace with DB or external service call.
        // Stub: return sample structure for any non-empty mobile; otherwise no data.
        $mobileNumber = trim($mobileNumber);
        if ($mobileNumber === '') {
            return null;
        }

        return [
            'customerId' => 58691733978856,
            'profile' => [
                'personalDetails' => [
                    'FirstName' => 'User',
                    'LastName' => 'G',
                    'MobileNumber' => $mobileNumber,
                    'EmailAddress' => 'user88@gmail.com',
                ],
                'currentAddress' => [
                    'street1' => 'NO. 172',
                    'street2' => 'NORTH VILLAGE STREET',
                    'postOffice' => '560003',
                    'pinCode' => '',
                    'city' => null,
                    'state' => 'Kerala',
                    'permanentAddress' => [
                        'street1' => 'NO. 172',
                        'street2' => 'NORTH VILLAGE STREET',
                        'postOffice' => '',
                        'pinCode' => '',
                        'city' => '',
                        'state' => 'Kerala',
                    ],
                ],
                'enrollmentList' => [
                    [
                        'PlanType' => 'Akshaya Priority Scheme',
                        'NomineeFirstName' => 'G',
                        'NomineeLastName' => '',
                        'NomineeRelationship' => 'SON',
                        'NomineeMobileNumber' => '',
                        'NomineeAddress' => 'NO. 172NORTH VILLAGE STREET',
                        'NomineeEmailAddress' => '',
                        'Status' => 'Open',
                        'Active' => true,
                        'CustomerID' => 58691733978856,
                        'JoinDate' => '2024-12-12 00:09:28',
                        'EndDate' => '2025-09-12',
                        'NoMonths' => 9,
                        'InitialMOP' => 'CARD',
                        'EMIAmount' => 1000,
                        'EnrollmentDayGoldRate' => 3100,
                        'EnrollmentID' => 68721733978856,
                        'SchemeID' => 1001,
                        'FeeAmount' => 0,
                        'IsMembershipFeeRequired' => 'Y',
                        'FinalRedeemableAmount' => 9000,
                        'SchemeEfficientType' => 'InEfficient',
                        'ReasonForInEfficient' => 'Not all the installments have been paid',
                        'SIONCC' => false,
                        'Emandate' => false,
                        'TransactionId' => '',
                        'DebitDate' => '',
                        'TotalPaidAmount' => 2000,
                        'collections' => [
                            [
                                'Active' => 'Y',
                                'Amount' => 1000,
                                'BankName' => 'test',
                                'BranchName' => null,
                                'CollectionDate' => '2024-12-12T00:09:28.000001',
                                'CollectionID' => 36,
                                'EnrollmentID' => 68721733978856,
                                'MOP' => 'CARD',
                                'EMIAmount' => 1000,
                            ],
                        ],
                    ],
                ],
                'IDProofStatus' => 'Not Verified',
                'IDProofType' => 'PASSPORT',
                'IDProofURL' => 'customer/proof/2024/12/12/testimage.jpeg',
                'IDProofNumber' => '123456778',
            ],
        ];
    }
}
