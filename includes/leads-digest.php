<?php
// File: includes/leads-digest.php
if ( ! defined( 'ABSPATH' ) ) exit;

add_shortcode( 'leads_digest', function() {
    global $wpdb;

    $subs = $wpdb->prefix . 'e_submissions';
    $vals = $wpdb->prefix . 'e_submissions_values';

    // verify
    if (
      $wpdb->get_var( $wpdb->prepare("SHOW TABLES LIKE %s", $subs) ) !== $subs ||
      $wpdb->get_var( $wpdb->prepare("SHOW TABLES LIKE %s", $vals) ) !== $vals
    ) {
        return '<p><em>No submissions table found.</em></p>';
    }

    $rows = $wpdb->get_results(
      "SELECT id, created_at FROM `{$subs}` ORDER BY created_at DESC"
    );
    if ( empty( $rows ) ) {
        return '<p><em>Your first lead is just around the corner — Stayed Tuned!</em></p>';
    }

    ob_start();
    ?>
    <!-- Inter + DataTables CSS -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.5/css/jquery.dataTables.min.css">

    <div class="leads-table-wrapper">
      <table class="leads-table">
        <thead>
          <tr>
            <th>Date</th>
            <th>Name</th>
            <th>Email</th>
            <th>Phone</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ( $rows as $row ):
            $fields = $wpdb->get_results(
              $wpdb->prepare(
                "SELECT `key`,`value` FROM `{$vals}` WHERE submission_id = %d",
                $row->id
              ), ARRAY_A
            );
            $m = [];
            foreach ( $fields as $f ) {
              $m[ $f['key'] ] = $f['value'];
            }
            $name  = trim( ($m['fname'] ?? '') . ' ' . ($m['lname'] ?? '') );
            $email = $m['email'] ?? '';
            $phone = $m['tel']   ?? '';
        ?>
          <tr>
            <td data-label="Date"><?php echo esc_html( date('Y-m-d H:i', strtotime($row->created_at)) ); ?></td>
            <td data-label="Name"><?php echo esc_html( $name ); ?></td>
            <td data-label="Email">
              <a href="mailto:<?php echo esc_attr($email); ?>"><?php echo esc_html($email); ?></a>
            </td>
            <td data-label="Phone">
              <a href="tel:<?php echo esc_attr($phone); ?>"><?php echo esc_html($phone); ?></a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- DataTables JS (ordering) -->
    <script src="https://cdn.datatables.net/1.13.5/js/jquery.dataTables.min.js"></script>
    <script>
    jQuery(function($){
      $('.leads-table').DataTable({
        paging:    false,
        info:      false,
        searching: false,
        ordering:  true,
        dom:       't'
      });
    });
    </script>

    <!-- Custom styling -->
    <style>
    /* container with side padding so borders are always visible */
    .leads-table-wrapper {
      font-family: 'Inter', sans-serif;
      margin: 2em auto;
      padding: 0 12px;
      box-sizing: border-box;
      width: 100%;
    }

    /* ensure border-included width */
    .leads-table,
    .leads-table th,
    .leads-table td {
      box-sizing: border-box;
    }

    .leads-table {
      width: 100%;
      border-collapse: separate;
      border-spacing: 0;
      background: #fff;
      border: 1px solid #e2e8f0;
      border-radius: 8px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.08);
      margin: 0 auto;
    }

    .leads-table th,
    .leads-table td {
      padding: 14px 16px;
    }

    .leads-table thead th {
      background: #f9fafb;
      color: #4b5563;
      font-size: 12px;
      font-weight: 600;
      text-transform: uppercase;
      border-bottom: 1px solid #e2e8f0;
      position: relative;
      cursor: pointer;
    }
    .leads-table thead th:after {
      content: '⇅';
      font-size: 10px;
      color: #cbd5e1;
      margin-left: 6px;
    }

    .leads-table tbody td {
      border-bottom: 1px solid #e2e8f0;
      color: #374151;
      font-size: 14px;
    }
    .leads-table tbody tr:hover td {
      background: #f1f5f9;
    }
    .leads-table tbody tr:last-child td:first-child {
      border-bottom-left-radius: 8px;
    }
    .leads-table tbody tr:last-child td:last-child {
      border-bottom-right-radius: 8px;
    }

    .leads-table a {
      color: #2563eb;
      text-decoration: none;
    }
    .leads-table a:hover {
      text-decoration: underline;
    }

    /* mobile cards */
    @media (max-width: 600px) {
      .leads-table {
        border: none !important;
        box-shadow: none !important;
      }
      .leads-table thead { display: none; }
      .leads-table,
      .leads-table tbody,
      .leads-table tr { display: block; width: 100%; }
      .leads-table tr {
        margin-bottom: 16px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
      }
      .leads-table td {
        display: flex;
        justify-content: space-between;
        padding: 12px;
        border: none;
        border-top: 1px solid #e2e8f0;
      }
      .leads-table td:first-child { border-top: none; }
      .leads-table td::before {
        content: attr(data-label);
        color: #6b7280;
        font-weight: 600;
        flex: 1;
      }
      .leads-table td a {
        flex: 2;
        text-align: right;
      }
    }
    </style>
    <?php
    return ob_get_clean();
});
