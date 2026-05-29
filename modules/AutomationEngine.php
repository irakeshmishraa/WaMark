<?php
/**
 * WaMark - Automation Engine Module
 * Manages workflow creation, enrollment, and step execution
 */

class AutomationEngine {
    private $db;
    private $userId;

    public function __construct($userId) {
        global $db;
        $this->db = $db;
        $this->userId = $userId;
    }

    /**
     * Create automation workflow
     */
    public function create($data) {
        $automationId = $this->db->insert('automations', [
            'user_id' => $this->userId,
            'name' => $data['name'],
            'type' => $data['type'] ?? 'trigger',
            'status' => 'draft',
            'trigger_type' => $data['trigger_type'] ?? 'keyword',
            'trigger_value' => $data['trigger_value'] ?? null,
            'whatsapp_account_id' => $data['whatsapp_account_id'] ?? null,
            'target_groups' => isset($data['target_groups']) ? json_encode($data['target_groups']) : null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // Add steps
        if (!empty($data['steps'])) {
            foreach ($data['steps'] as $i => $step) {
                $this->addStep($automationId, $i + 1, $step);
            }
        }

        return $automationId;
    }

    /**
     * Add step to automation
     */
    public function addStep($automationId, $order, $stepData) {
        return $this->db->insert('automation_steps', [
            'automation_id' => $automationId,
            'step_order' => $order,
            'action_type' => $stepData['action_type'] ?? 'send_message',
            'message_type' => $stepData['message_type'] ?? 'text',
            'message_body' => $stepData['message_body'] ?? null,
            'media_url' => $stepData['media_url'] ?? null,
            'template_id' => $stepData['template_id'] ?? null,
            'delay_value' => (int)($stepData['delay_value'] ?? 0),
            'delay_unit' => $stepData['delay_unit'] ?? 'hours',
            'condition_field' => $stepData['condition_field'] ?? null,
            'condition_operator' => $stepData['condition_operator'] ?? null,
            'condition_value' => $stepData['condition_value'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Enroll contact in automation
     */
    public function enroll($automationId, $contactId) {
        // Check if already enrolled
        $existing = $this->db->exists('automation_enrollments',
            'automation_id = ? AND contact_id = ? AND status = ?',
            [$automationId, $contactId, 'active']
        );
        if ($existing) return false;

        // Get first step delay
        $firstStep = $this->db->fetch(
            "SELECT * FROM " . $this->db->table('automation_steps') . " WHERE automation_id = ? ORDER BY step_order LIMIT 1",
            [$automationId]
        );

        $nextAction = date('Y-m-d H:i:s');
        if ($firstStep && $firstStep['delay_value'] > 0) {
            $nextAction = date('Y-m-d H:i:s', strtotime("+{$firstStep['delay_value']} {$firstStep['delay_unit']}"));
        }

        $enrollmentId = $this->db->insert('automation_enrollments', [
            'automation_id' => $automationId,
            'contact_id' => $contactId,
            'current_step' => 1,
            'status' => 'active',
            'next_action_at' => $nextAction,
            'enrolled_at' => date('Y-m-d H:i:s'),
        ]);

        // Update automation counter
        $this->db->query(
            "UPDATE " . $this->db->table('automations') . " SET total_enrolled = total_enrolled + 1 WHERE id = ?",
            [$automationId]
        );

        return $enrollmentId;
    }

    /**
     * Bulk enroll contacts (from group or all)
     */
    public function bulkEnroll($automationId, $contactIds) {
        $enrolled = 0;
        foreach ($contactIds as $contactId) {
            if ($this->enroll($automationId, $contactId)) {
                $enrolled++;
            }
        }
        return $enrolled;
    }

    /**
     * Pause enrollment
     */
    public function pauseEnrollment($enrollmentId) {
        return $this->db->update('automation_enrollments', ['status' => 'paused'], 'id = ?', [$enrollmentId]);
    }

    /**
     * Resume enrollment
     */
    public function resumeEnrollment($enrollmentId) {
        return $this->db->update('automation_enrollments', [
            'status' => 'active',
            'next_action_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$enrollmentId]);
    }

    /**
     * Cancel enrollment
     */
    public function cancelEnrollment($enrollmentId) {
        return $this->db->update('automation_enrollments', ['status' => 'cancelled'], 'id = ?', [$enrollmentId]);
    }

    /**
     * Activate automation
     */
    public function activate($automationId) {
        return $this->db->update('automations', ['status' => 'active'], 'id = ? AND user_id = ?', [$automationId, $this->userId]);
    }

    /**
     * Deactivate automation
     */
    public function deactivate($automationId) {
        return $this->db->update('automations', ['status' => 'inactive'], 'id = ? AND user_id = ?', [$automationId, $this->userId]);
    }

    /**
     * Delete automation and all related data
     */
    public function delete($automationId) {
        $this->db->delete('automation_enrollments', 'automation_id = ?', [$automationId]);
        $this->db->delete('automation_steps', 'automation_id = ?', [$automationId]);
        $this->db->delete('automations', 'id = ? AND user_id = ?', [$automationId, $this->userId]);
        return true;
    }

    /**
     * Get automation statistics
     */
    public function getStats($automationId) {
        $auto = $this->db->fetch("SELECT * FROM " . $this->db->table('automations') . " WHERE id = ?", [$automationId]);
        $steps = $this->db->fetchAll("SELECT * FROM " . $this->db->table('automation_steps') . " WHERE automation_id = ? ORDER BY step_order", [$automationId]);
        $activeEnrollments = $this->db->count('automation_enrollments', 'automation_id = ? AND status = ?', [$automationId, 'active']);
        $completedEnrollments = $this->db->count('automation_enrollments', 'automation_id = ? AND status = ?', [$automationId, 'completed']);

        return [
            'automation' => $auto,
            'steps' => $steps,
            'active_enrollments' => $activeEnrollments,
            'completed_enrollments' => $completedEnrollments,
            'total_enrolled' => $auto['total_enrolled'] ?? 0,
        ];
    }
}
