<?php
/**
 * EduPro LMS - Manual / Bank Transfer Gateway (UK & India)
 *
 * Supports both India bank details (Bank Name, Account Name, Account Number, IFSC, UPI ID)
 * and UK bank details (Account Name, Sort Code, Account Number, IBAN).
 *
 * Strictly creates PENDING orders only — NEVER auto-completes or auto-enrolls.
 * Admin manually approves received transfers from the Orders & Reconciliation screen.
 *
 * @package EduPro
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EduPro_Gateway_Bank_Transfer extends EduPro_Payment_Gateway {

    public function get_id() {
        return 'bank_transfer';
    }

    public function get_label() {
        return __( 'Direct Bank Transfer (UK & India)', 'edupro' );
    }

    public function get_description() {
        return __( 'Transfer fees directly via UK Faster Payments / BACS or Indian NEFT / IMPS / UPI. Access activates after verification.', 'edupro' );
    }

    public function get_region() {
        return __( 'UK & India', 'edupro' );
    }

    public function get_badge() {
        return __( 'Manual Approval · UK & India', 'edupro' );
    }

    public function get_priority() {
        return 50;
    }

    public function get_supported_currencies() {
        return array( 'GBP', 'INR', 'USD' );
    }

    public function is_test_mode() {
        return false;
    }

    public function is_enabled() {
        return get_option( 'edupro_bank_transfer_enabled', '0' ) === '1';
    }

    public function get_bank_details() {
        return array(
            'india'        => array(
                'bank_name'      => $this->get_trimmed_option( 'edupro_bank_name' ),
                'account_name'   => $this->get_trimmed_option( 'edupro_bank_account_name' ),
                'account_number' => $this->get_trimmed_option( 'edupro_bank_account_number' ),
                'ifsc'           => $this->get_trimmed_option( 'edupro_bank_ifsc' ),
                'upi_id'         => $this->get_trimmed_option( 'edupro_bank_upi_id' ),
            ),
            'uk'           => array(
                'account_name'   => $this->get_trimmed_option( 'edupro_bank_uk_account_name' ),
                'sort_code'      => $this->get_trimmed_option( 'edupro_bank_uk_sort_code' ),
                'account_number' => $this->get_trimmed_option( 'edupro_bank_uk_account_number' ),
                'iban'           => $this->get_trimmed_option( 'edupro_bank_uk_iban' ),
            ),
            'instructions' => trim( (string) get_option( 'edupro_bank_instructions', '' ) ),
        );
    }

    public function get_client_config() {
        return $this->get_bank_details();
    }

    /**
     * Create a pending bank transfer payment instruction payload and email the student.
     * NEVER marks the order completed or enrolls the student.
     *
     * @param object  $order
     * @param WP_Post $course
     * @param WP_User $user
     * @return array|WP_Error
     */
    public function create_payment( $order, $course, $user ) {
        if ( ! $this->is_enabled() ) {
            return new WP_Error( 'bank_transfer_disabled', __( 'Bank transfer is not currently enabled.', 'edupro' ) );
        }

        $details = $this->get_bank_details();

        // Email transfer instructions to student
        edupro_send_bank_transfer_instructions_email(
            absint( $user->ID ),
            absint( $course->ID ),
            $order->order_id,
            floatval( $order->amount ),
            strtoupper( (string) $order->currency )
        );

        edupro_log_payment_event(
            $order->order_id,
            $this->get_id(),
            'bank_transfer_created',
            'pending',
            sprintf( 'Pending manual bank transfer (%s %.2f)', strtoupper( $order->currency ), floatval( $order->amount ) )
        );

        return array(
            'gateway'           => $this->get_id(),
            'gateway_order_id'  => 'BANK-' . $order->order_id,
            'status'            => 'pending',
            'currency'          => strtoupper( (string) $order->currency ),
            'amount'            => round( floatval( $order->amount ), 2 ),
            'instructions'      => $details['instructions'],
            'india'             => $details['india'],
            'uk'                => $details['uk'],
            // Flat convenience keys for templates
            'bank_name'         => $details['india']['bank_name'],
            'account_name'      => $details['india']['account_name'],
            'account_number'    => $details['india']['account_number'],
            'ifsc'              => $details['india']['ifsc'],
            'upi_id'            => $details['india']['upi_id'],
            'uk_account_name'   => $details['uk']['account_name'],
            'uk_sort_code'      => $details['uk']['sort_code'],
            'uk_account_number' => $details['uk']['account_number'],
            'uk_iban'           => $details['uk']['iban'],
            'message'           => __( 'Order created! Please transfer the fee using the bank details below and quote your Order ID as the reference. Your enrollment will be activated once our team verifies the transfer.', 'edupro' ),
        );
    }

    /**
     * Bank transfers cannot be auto-verified via client callback.
     *
     * @param object $order
     * @param array  $payload
     * @return array
     */
    public function verify_payment( $order, $payload = array() ) {
        return array(
            'verified'       => false,
            'status'         => $order->status,
            'transaction_id' => $order->transaction_id,
            'amount_paid'    => 0.0,
            'currency'       => strtoupper( (string) $order->currency ),
            'message'        => __( 'Bank transfers require manual administrator verification.', 'edupro' ),
            'raw'            => array(),
        );
    }

    /**
     * Bank transfers do not support automated webhooks.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function handle_webhook( WP_REST_Request $request ) {
        return new WP_REST_Response( array( 'error' => 'Bank transfer does not support webhooks.' ), 405 );
    }

    /**
     * Record a manual bank refund reference.
     *
     * @param object     $order
     * @param float|null $amount
     * @param string     $reason
     * @return array|WP_Error
     */
    public function refund_payment( $order, $amount = null, $reason = '' ) {
        $refund_amount = $amount !== null ? round( floatval( $amount ), 2 ) : round( floatval( $order->amount ), 2 );
        if ( $refund_amount <= 0 || $refund_amount > floatval( $order->amount ) + 0.01 ) {
            return new WP_Error( 'bank_refund_amount', __( 'Invalid refund amount.', 'edupro' ) );
        }

        return array(
            'refund_id' => 'MANUAL-REF-' . absint( $order->id ) . '-' . time(),
            'amount'    => $refund_amount,
            'status'    => 'manual_recorded',
            'raw'       => array( 'note' => sanitize_text_field( $reason ) ),
        );
    }

    /**
     * Bank transfers are reconciled manually by staff inspecting bank statements.
     *
     * @param object $order
     * @return array
     */
    public function reconcile_order( $order ) {
        return array(
            'verified'       => $order->status === 'completed',
            'status'         => $order->status,
            'transaction_id' => $order->transaction_id,
            'amount_paid'    => $order->status === 'completed' ? floatval( $order->amount ) : 0.0,
            'currency'       => strtoupper( (string) $order->currency ),
            'message'        => $order->status === 'completed'
                ? __( 'Already manually approved.', 'edupro' )
                : __( 'Awaiting manual bank transfer verification. Use Approve once funds arrive.', 'edupro' ),
            'raw'            => array(),
        );
    }
}
