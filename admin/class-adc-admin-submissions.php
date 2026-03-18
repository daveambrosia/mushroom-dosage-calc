<?php
/**
 * Admin Submissions Management
 *
 * Handles submission list, bulk actions, blacklist management.
 *
 * @since 2.13.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class ADC_Admin_Submissions {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // No hooks needed — rendering only
    }

    /**
     * Submissions page with bulk actions and blacklist
     */
    public function render_submissions() {
        $status = isset($_GET['status']) ? sanitize_key($_GET['status']) : 'pending';
        $view = isset($_GET['view']) ? sanitize_key($_GET['view']) : 'submissions';
        
        // Handle bulk actions
        if (isset($_POST['adc_bulk_action']) && isset($_POST['adc_bulk_nonce'])) {
            if (!wp_verify_nonce($_POST['adc_bulk_nonce'], 'adc_bulk_submissions')) {
                wp_die('Security check failed');
            }
            
            $action = sanitize_key($_POST['bulk_action']);
            $ids = isset($_POST['submission_ids']) ? array_map('intval', $_POST['submission_ids']) : array();
            
            if (!empty($ids) && !empty($action)) {
                if ($action === 'approve') {
                    $result = ADC_Submissions::bulk_approve($ids);
                    echo '<div class="notice notice-success"><p>Approved ' . $result['success'] . ' submissions. ' . ($result['failed'] > 0 ? $result['failed'] . ' failed.' : '') . '</p></div>';
                } elseif ($action === 'reject') {
                    $result = ADC_Submissions::bulk_reject($ids);
                    echo '<div class="notice notice-success"><p>Rejected ' . $result['success'] . ' submissions.</p></div>';
                } elseif ($action === 'delete') {
                    $result = ADC_Submissions::bulk_delete($ids);
                    echo '<div class="notice notice-success"><p>Deleted ' . $result['success'] . ' submissions.</p></div>';
                }
            }
        }
        
        // Handle single actions
        if (isset($_GET['action']) && isset($_GET['id']) && isset($_GET['_wpnonce'])) {
            if (!wp_verify_nonce($_GET['_wpnonce'], 'adc_submission_action')) {
                wp_die('Security check failed');
            }
            
            $id = intval($_GET['id']);
            $action = sanitize_key($_GET['action']);
            
            if ($action === 'approve') {
                $result = ADC_Submissions::approve($id);
                if (!is_wp_error($result)) {
                    echo '<div class="notice notice-success"><p>Submission approved and added to database!</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>' . esc_html($result->get_error_message()) . '</p></div>';
                }
            } elseif ($action === 'reject') {
                ADC_Submissions::reject($id);
                echo '<div class="notice notice-success"><p>Submission rejected.</p></div>';
            } elseif ($action === 'delete') {
                ADC_Submissions::delete($id);
                echo '<div class="notice notice-success"><p>Submission deleted.</p></div>';
            } elseif ($action === 'block') {
                $blocked = ADC_Submissions::block_submitter($id, true, true, 'Blocked via submissions page');
                if (!empty($blocked)) {
                    ADC_Submissions::reject($id, 'Blocked submitter');
                    echo '<div class="notice notice-success"><p>Submitter blocked (' . implode(', ', $blocked) . ') and submission rejected.</p></div>';
                } else {
                    echo '<div class="notice notice-warning"><p>No IP or email to block.</p></div>';
                }
            }
        }
        
        // Handle blacklist actions
        if (isset($_POST['adc_blacklist_action']) && isset($_POST['adc_blacklist_nonce'])) {
            if (!wp_verify_nonce($_POST['adc_blacklist_nonce'], 'adc_blacklist')) {
                wp_die('Security check failed');
            }
            
            $bl_action = sanitize_key($_POST['blacklist_action']);
            
            if ($bl_action === 'add') {
                $type = sanitize_key($_POST['bl_type']);
                $value = sanitize_text_field($_POST['bl_value']);
                $reason = sanitize_textarea_field($_POST['bl_reason']);
                
                if ($type && $value) {
                    ADC_Submissions::add_to_blacklist($type, $value, $reason);
                    echo '<div class="notice notice-success"><p>Added to blacklist.</p></div>';
                }
            } elseif ($bl_action === 'remove' && isset($_POST['bl_id'])) {
                ADC_Submissions::remove_from_blacklist(intval($_POST['bl_id']));
                echo '<div class="notice notice-success"><p>Removed from blacklist.</p></div>';
            }
        }
        
        $submissions = ADC_Submissions::get_all(array('status' => $status === 'all' ? null : $status));
        $counts = ADC_Submissions::count_by_status();
        $blacklist = ADC_Submissions::get_blacklist();
        
        ?>
        <div class="wrap adc-admin">
            <h1>Submissions</h1>
            
            <h2 class="nav-tab-wrapper">
                <a href="?page=dosage-calculator-submissions&view=submissions" class="nav-tab <?php echo $view === 'submissions' ? 'nav-tab-active' : ''; ?>">Submissions</a>
                <a href="?page=dosage-calculator-submissions&view=blacklist" class="nav-tab <?php echo $view === 'blacklist' ? 'nav-tab-active' : ''; ?>">Blacklist (<?php echo count($blacklist); ?>)</a>
            </h2>
            
            <?php if ($view === 'blacklist'): ?>
                <!-- Blacklist View -->
                <div class="adc-two-col adc-mt-10">
                    <div class="adc-col">
                        <h3>Blocked IPs & Emails</h3>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Value</th>
                                    <th>Reason</th>
                                    <th>Added</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($blacklist)): ?>
                                    <tr><td colspan="5">No blocked entries.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($blacklist as $entry): ?>
                                        <tr>
                                            <td data-label="Type"><strong><?php echo strtoupper(esc_html($entry['type'])); ?></strong></td>
                                            <td data-label="Value"><span class="adc-mobile-label">Value: </span><code><?php echo esc_html($entry['value']); ?></code></td>
                                            <td data-label="Reason"><span class="adc-mobile-label">Reason: </span><?php echo esc_html($entry['reason'] ?: '-'); ?></td>
                                            <td data-label="Added"><span class="adc-mobile-label">Added: </span><?php echo esc_html(wp_date('M j, Y', strtotime($entry['created_at']))); ?></td>
                                            <td data-label="Actions" class="adc-actions-cell">
                                                <form method="post" class="adc-inline-form">
                                                    <?php wp_nonce_field('adc_blacklist', 'adc_blacklist_nonce'); ?>
                                                    <input type="hidden" name="blacklist_action" value="remove">
                                                    <input type="hidden" name="bl_id" value="<?php echo $entry['id']; ?>">
                                                    <button type="submit" class="button button-small adc-btn-delete" onclick="return adcConfirmSync(event,'Remove this entry from the blacklist?', { title: 'Remove from Blacklist', confirmText: 'Remove', danger: true });">Remove</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="adc-col">
                        <h3>Add to Blacklist</h3>
                        <form method="post">
                            <?php wp_nonce_field('adc_blacklist', 'adc_blacklist_nonce'); ?>
                            <input type="hidden" name="blacklist_action" value="add">
                            <table class="form-table">
                                <tr>
                                    <th><label for="bl_type">Type</label></th>
                                    <td>
                                        <select name="bl_type" id="bl_type" required>
                                            <option value="ip">IP Address</option>
                                            <option value="email">Email</option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="bl_value">Value</label></th>
                                    <td><input type="text" name="bl_value" id="bl_value" class="regular-text" required placeholder="e.g., 192.168.1.1 or spam@example.com"></td>
                                </tr>
                                <tr>
                                    <th><label for="bl_reason">Reason</label></th>
                                    <td><textarea name="bl_reason" id="bl_reason" rows="2" class="large-text" placeholder="Optional reason for blocking"></textarea></td>
                                </tr>
                            </table>
                            <?php submit_button('Add to Blacklist'); ?>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <!-- Submissions View -->
                <ul class="subsubsub">
                    <li><a href="?page=dosage-calculator-submissions&status=all" <?php echo $status === 'all' ? 'class="current"' : ''; ?>>All <span class="count">(<?php echo $counts['total']; ?>)</span></a> |</li>
                    <li><a href="?page=dosage-calculator-submissions&status=pending" <?php echo $status === 'pending' ? 'class="current"' : ''; ?>>Pending <span class="count">(<?php echo $counts['pending']; ?>)</span></a> |</li>
                    <li><a href="?page=dosage-calculator-submissions&status=approved" <?php echo $status === 'approved' ? 'class="current"' : ''; ?>>Approved <span class="count">(<?php echo $counts['approved']; ?>)</span></a> |</li>
                    <li><a href="?page=dosage-calculator-submissions&status=rejected" <?php echo $status === 'rejected' ? 'class="current"' : ''; ?>>Rejected <span class="count">(<?php echo $counts['rejected']; ?>)</span></a></li>
                </ul>
                
                <form method="post" id="adc-submissions-form">
                    <?php wp_nonce_field('adc_bulk_submissions', 'adc_bulk_nonce'); ?>
                    <input type="hidden" name="adc_bulk_action" value="1">
                    
                    <div class="tablenav top">
                        <div class="alignleft actions bulkactions">
                            <select name="bulk_action" id="bulk-action-selector-top">
                                <option value="">Bulk Actions</option>
                                <?php if ($status === 'pending'): ?>
                                    <option value="approve">Approve</option>
                                    <option value="reject">Reject</option>
                                <?php endif; ?>
                                <option value="delete">Delete</option>
                            </select>
                            <input type="submit" class="button action" value="Apply">
                        </div>
                    </div>
                    
                    <table class="wp-list-table widefat fixed striped adc-submissions-table" id="adc-submissions-table">
                        <thead>
                            <tr>
                                <td class="manage-column column-cb check-column">
                                    <input type="checkbox" id="cb-select-all-1" onclick="jQuery('input[name=\'submission_ids[]\']').prop('checked', this.checked);">
                                </td>
                                <th>Type</th>
                                <th>Name</th>
                                <th>Submitter</th>
                                <th>Submitted</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($submissions)): ?>
                                <tr><td colspan="7">No submissions found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($submissions as $sub): 
                                    $data = json_decode(wp_unslash($sub['data']), true);
                                    $name = $data['name'] ?? 'Unknown';
                                ?>
                                    <tr>
                                        <th scope="row" class="check-column adc-mobile-hide">
                                            <input type="checkbox" name="submission_ids[]" value="<?php echo $sub['id']; ?>">
                                        </th>
                                        <td data-label="Type"><span class="adc-mobile-label">Type: </span><span class="adc-badge adc-badge-<?php echo esc_attr($sub['type']); ?>"><?php echo esc_html(ucfirst($sub['type'])); ?></span></td>
                                        <td data-label="Name">
                                            <strong><?php echo esc_html($name); ?></strong>
                                            <?php if ($sub['type'] === 'strain' && isset($data['psilocybin'])): ?>
                                                <br><small>Psilocybin: <?php echo number_format($data['psilocybin']); ?> mcg/g</small>
                                                <?php if (!empty($data['psilocin'])): ?>
                                                    <br><small>Psilocin: <?php echo number_format($data['psilocin']); ?> mcg/g</small>
                                                <?php endif; ?>
                                            <?php elseif ($sub['type'] === 'edible' && isset($data['psilocybin'])): ?>
                                                <br><small><?php echo number_format($data['psilocybin']); ?> mcg/pkg</small>
                                                <br><small><?php echo intval($data['piecesPerPackage'] ?? $data['pieces_per_package'] ?? 1); ?> pieces/pkg</small>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="Submitter">
                                            <span class="adc-mobile-label">From: </span>
                                            <?php if (!empty($sub['submitter_name'])): ?>
                                                <strong><?php echo esc_html($sub['submitter_name']); ?></strong><br>
                                            <?php endif; ?>
                                            <?php if (!empty($sub['submitter_email'])): ?>
                                                <a href="mailto:<?php echo esc_attr($sub['submitter_email']); ?>"><?php echo esc_html($sub['submitter_email']); ?></a>
                                            <?php else: ?>
                                                <em>Anonymous</em>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="Date"><span class="adc-mobile-label">Submitted: </span><?php echo esc_html(wp_date('M j, Y g:ia', strtotime($sub['created_at']))); ?></td>
                                        <td data-label="Status">
                                            <?php if ($sub['status'] === 'pending'): ?>
                                                <span class="adc-status-pending">● Pending</span>
                                            <?php elseif ($sub['status'] === 'approved'): ?>
                                                <span class="adc-status-approved">● Approved</span>
                                            <?php else: ?>
                                                <span class="adc-status-rejected">● Rejected</span>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="Actions" class="adc-actions-cell">
                                            <button type="button" class="button button-small" onclick="adcShowDetailsModal(<?php echo htmlspecialchars(wp_json_encode(array_merge($sub, array('data' => $data))), ENT_QUOTES, 'UTF-8'); ?>)">View</button>
                                            <?php if ($sub['status'] === 'pending'): ?>
                                                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=dosage-calculator-submissions&action=approve&id=' . $sub['id'] . '&status=' . $status), 'adc_submission_action'); ?>" class="button button-small adc-btn-approve">Approve</a>
                                                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=dosage-calculator-submissions&action=reject&id=' . $sub['id'] . '&status=' . $status), 'adc_submission_action'); ?>" class="button button-small adc-btn-reject">Reject</a>
                                            <?php endif; ?>
                                            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=dosage-calculator-submissions&action=block&id=' . $sub['id'] . '&status=' . $status), 'adc_submission_action'); ?>" class="button button-small adc-btn-warning" onclick="return adcConfirmSync(event,'Block this submitter (IP + email) and reject their submission?', { title: 'Block Submitter', confirmText: 'Block', danger: true });" title="Block IP &amp; Email">Block</a>
                                            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=dosage-calculator-submissions&action=delete&id=' . $sub['id'] . '&status=' . $status), 'adc_submission_action'); ?>" class="button button-small adc-btn-delete" onclick="return adcConfirmSync(event,'Delete this submission? This cannot be undone.', { title: 'Delete Submission', confirmText: 'Delete', danger: true });">Delete</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    
                    <!-- Mobile Card View -->
                    <div class="adc-mobile-cards">
                        <?php if (empty($submissions)): ?>
                            <p>No submissions found.</p>
                        <?php else: ?>
                            <?php foreach ($submissions as $sub): 
                                $data = json_decode(wp_unslash($sub['data']), true);
                                $name = $data['name'] ?? 'Unknown';
                                $statusClass = $sub['status'] === 'pending' ? 'pending' : ($sub['status'] === 'approved' ? 'approved' : 'rejected');
                            ?>
                                <div class="adc-submission-card adc-submission-<?php echo $statusClass; ?>">
                                    <div class="adc-submission-header">
                                        <div class="adc-submission-name"><?php echo esc_html($name); ?></div>
                                        <span class="adc-badge adc-badge-<?php echo esc_attr($sub['type']); ?>"><?php echo esc_html(ucfirst($sub['type'])); ?></span>
                                    </div>
                                    
                                    <div class="adc-submission-details">
                                        <?php if ($sub['type'] === 'strain' && isset($data['psilocybin'])): ?>
                                            <div class="adc-detail-row">
                                                <span class="adc-detail-label">Psilocybin:</span>
                                                <span class="adc-detail-value"><?php echo number_format($data['psilocybin']); ?> mcg/g</span>
                                            </div>
                                            <?php if (!empty($data['psilocin'])): ?>
                                            <div class="adc-detail-row">
                                                <span class="adc-detail-label">Psilocin:</span>
                                                <span class="adc-detail-value"><?php echo number_format($data['psilocin']); ?> mcg/g</span>
                                            </div>
                                            <?php endif; ?>
                                        <?php elseif ($sub['type'] === 'edible' && isset($data['psilocybin'])): ?>
                                            <div class="adc-detail-row">
                                                <span class="adc-detail-label">Psilocybin:</span>
                                                <span class="adc-detail-value"><?php echo number_format($data['psilocybin']); ?> mcg/pkg</span>
                                            </div>
                                            <div class="adc-detail-row">
                                                <span class="adc-detail-label">Pieces:</span>
                                                <span class="adc-detail-value"><?php echo intval($data['piecesPerPackage'] ?? $data['pieces_per_package'] ?? 1); ?> per pkg</span>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="adc-detail-row">
                                            <span class="adc-detail-label">From:</span>
                                            <span class="adc-detail-value">
                                                <?php if (!empty($sub['submitter_name'])): ?>
                                                    <?php echo esc_html($sub['submitter_name']); ?>
                                                <?php elseif (!empty($sub['submitter_email'])): ?>
                                                    <?php echo esc_html($sub['submitter_email']); ?>
                                                <?php else: ?>
                                                    Anonymous
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                        
                                        <div class="adc-detail-row">
                                            <span class="adc-detail-label">Date:</span>
                                            <span class="adc-detail-value"><?php echo esc_html(wp_date('M j, Y', strtotime($sub['created_at']))); ?></span>
                                        </div>
                                        
                                        <div class="adc-detail-row">
                                            <span class="adc-detail-label">Status:</span>
                                            <span class="adc-status-<?php echo $statusClass; ?>">● <?php echo ucfirst($sub['status']); ?></span>
                                        </div>
                                    </div>
                                    
                                    <div class="adc-submission-actions">
                                        <button type="button" class="button" onclick="adcShowDetailsModal(<?php echo htmlspecialchars(wp_json_encode(array_merge($sub, array('data' => $data))), ENT_QUOTES, 'UTF-8'); ?>)">View Details</button>
                                        <?php if ($sub['status'] === 'pending'): ?>
                                            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=dosage-calculator-submissions&action=approve&id=' . $sub['id'] . '&status=' . $status), 'adc_submission_action'); ?>" class="button adc-btn-approve">Approve</a>
                                            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=dosage-calculator-submissions&action=reject&id=' . $sub['id'] . '&status=' . $status), 'adc_submission_action'); ?>" class="button adc-btn-reject">Reject</a>
                                            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=dosage-calculator-submissions&action=block&id=' . $sub['id'] . '&status=' . $status), 'adc_submission_action'); ?>" class="button adc-btn-warning" onclick="return adcConfirmSync(event,'Block this submitter (IP + email)?', { title: 'Block Submitter', confirmText: 'Block', danger: true });">Block</a>
                                        <?php endif; ?>
                                        <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=dosage-calculator-submissions&action=delete&id=' . $sub['id'] . '&status=' . $status), 'adc_submission_action'); ?>" class="button adc-btn-delete" onclick="return adcConfirmSync(event,'Delete this submission? This cannot be undone.', { title: 'Delete Submission', confirmText: 'Delete', danger: true });">Delete</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </form>
                <!-- Notes Modal -->
                <div id="adc-notes-modal" class="adc-notes-modal" role="dialog" aria-modal="true" aria-labelledby="adc-notes-modal-title" onclick="if(event.target===this)adcCloseNotesModal()">
                    <div class="adc-notes-modal-content">
                        <div class="adc-notes-modal-header">
                            <h3 id="adc-notes-modal-title">📝 Submission Notes</h3>
                            <button type="button" class="adc-notes-modal-close" onclick="adcCloseNotesModal()" aria-label="Close notes modal">&times;</button>
                        </div>
                        <div class="adc-notes-modal-body" id="adc-notes-content"></div>
                    </div>
                </div>

                <!-- Details Modal -->
                <div id="adc-details-modal" class="adc-notes-modal" role="dialog" aria-modal="true" aria-labelledby="adc-details-modal-title" onclick="if(event.target===this)adcCloseDetailsModal()">
                    <div class="adc-notes-modal-content adc-details-modal-content">
                        <div class="adc-notes-modal-header">
                            <h3 id="adc-details-modal-title">📋 Submission Details</h3>
                            <button type="button" class="adc-notes-modal-close" onclick="adcCloseDetailsModal()" aria-label="Close details modal">&times;</button>
                        </div>
                        <div class="adc-notes-modal-body adc-details-modal-body" id="adc-details-content"></div>
                    </div>
                </div>

            <?php endif; ?>
        </div>
        <?php
    }
}
