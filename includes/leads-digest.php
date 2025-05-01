<?php
// File: includes/leads-digest.php
if ( ! defined( 'ABSPATH' ) ) exit;

add_shortcode( 'site_url', function() {
    return esc_url( site_url() );
} );

add_shortcode( 'leads_digest', function() {
    global $wpdb;

    $subs_table = $wpdb->prefix . 'e_submissions';
    $vals_table = $wpdb->prefix . 'e_submissions_values';

    // Verify tables
    if ( $wpdb->get_var( $wpdb->prepare("SHOW TABLES LIKE %s", $subs_table) ) !== $subs_table ) {
        return '<p><em>No submissions table found.</em></p>';
    }
    if ( $wpdb->get_var( $wpdb->prepare("SHOW TABLES LIKE %s", $vals_table) ) !== $vals_table ) {
        return '<p><em>No submissions-values table found.</em></p>';
    }

    // Fetch entries
    $rows = $wpdb->get_results(
        "SELECT id, created_at
         FROM `{$subs_table}`
         ORDER BY created_at DESC"
    );
    if ( empty( $rows ) ) {
        return '<p><em>No leads yet.</em></p>';
    }

    ob_start();
    ?>
    <table style="width:100%;border-collapse:collapse;font-family:Arial,sans-serif;">
      <thead>
        <tr>
          <th style="border:1px solid #ddd;padding:8px;">Date</th>
          <th style="border:1px solid #ddd;padding:8px;">Name</th>
          <th style="border:1px solid #ddd;padding:8px;">Email</th>
          <th style="border:1px solid #ddd;padding:8px;">Phone</th>
        </tr>
      </thead>
      <tbody>
    <?php
    foreach ( $rows as $row ) {
        $field_rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT `key`,`value`
                 FROM `{$vals_table}`
                 WHERE submission_id = %d",
                $row->id
            ),
            ARRAY_A
        );
        $map = [];
        foreach ( $field_rows as $f ) {
            $map[ $f['key'] ] = $f['value'];
        }

        $fname = $map['fname'] ?? '';
        $lname = $map['lname'] ?? '';
        $name  = trim( $fname . ' ' . $lname );
        $email = $map['email'] ?? '';
        $phone = $map['tel']   ?? '';

        printf(
            '<tr>
               <td style="border:1px solid #ddd;padding:8px;">%1$s</td>
               <td style="border:1px solid #ddd;padding:8px;">%2$s</td>
               <td style="border:1px solid #ddd;padding:8px;"><a href="mailto:%3$s">%3$s</a></td>
               <td style="border:1px solid #ddd;padding:8px;"><a href="tel:%4$s">%4$s</a></td>
             </tr>',
            esc_html( date('Y-m-d H:i', strtotime($row->created_at)) ),
            esc_html( $name ),
            esc_html( $email ),
            esc_html( $phone )
        );
    }
    ?>
      </tbody>
    </table>
    <?php
    return ob_get_clean();
} );
