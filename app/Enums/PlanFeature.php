<?php

namespace App\Enums;

enum PlanFeature: string
{
    // Business features
    case AuditLogs = 'audit_logs';
    case Billing = 'billing';
    case SpamManagement = 'spam_management';
    case ServiceReports = 'service_reports';
    case Attachments = 'attachments';
    case AgentSchedule = 'agent_schedule';
    case SlaManagement = 'sla_management';
    case SlaReport = 'sla_report';
    case EmailNotifications = 'email_notifications';
    case DetailedReporting = 'detailed_reporting';

    // Enterprise features
    case TicketMerging = 'ticket_merging';
    case TicketReopening = 'ticket_reopening';
    case CustomRoles = 'custom_roles';
    case DepartmentManagement = 'department_management';
    case AgentEscalation = 'agent_escalation';
    case ClientComments = 'client_comments';
    case KnowledgeBase = 'knowledge_base';
    case CannedResponses = 'canned_responses';

    /**
     * Get features included in the Starter plan.
     * Starter has no feature-gated items — all core features are built-in:
     * Dashboard, Ticket CRUD, Task Checklist, Products/Services, Categories,
     * User Roles (Admin/Manager/Agent), Fixed Departments, Basic Reporting.
     *
     * @return list<self>
     */
    public static function starterFeatures(): array
    {
        return [];
    }

    /**
     * Get features included in the Business plan.
     *
     * @return list<self>
     */
    public static function businessFeatures(): array
    {
        return [
            ...self::starterFeatures(),
            self::AuditLogs,
            self::Billing,
            self::SpamManagement,
            self::ServiceReports,
            self::Attachments,
            self::AgentSchedule,
            self::SlaManagement,
            self::SlaReport,
            self::EmailNotifications,
            self::DetailedReporting,
        ];
    }

    /**
     * Get features included in the Enterprise plan.
     *
     * @return list<self>
     */
    public static function enterpriseFeatures(): array
    {
        return [
            ...self::businessFeatures(),
            self::TicketMerging,
            self::TicketReopening,
            self::CustomRoles,
            self::DepartmentManagement,
            self::AgentEscalation,
            self::ClientComments,
            self::KnowledgeBase,
            self::CannedResponses,
        ];
    }

    /**
     * Get the feature values for a given plan slug.
     *
     * @return list<string>
     */
    public static function forPlan(string $planSlug): array
    {
        return match ($planSlug) {
            'start' => array_map(fn (self $f) => $f->value, self::starterFeatures()),
            'business' => array_map(fn (self $f) => $f->value, self::businessFeatures()),
            'enterprise' => array_map(fn (self $f) => $f->value, self::enterpriseFeatures()),
            default => [],
        };
    }

    /**
     * Get a human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::AuditLogs => 'Ticket Activity History (Audit Logs)',
            self::Billing => 'Billing',
            self::SpamManagement => 'Mark as Spam',
            self::ServiceReports => 'Auto Generated Service Report',
            self::Attachments => 'Attachments',
            self::AgentSchedule => 'Agent Availability Schedule',
            self::SlaManagement => 'SLA Management',
            self::SlaReport => 'SLA Compliance Report',
            self::EmailNotifications => 'Email Notification',
            self::DetailedReporting => 'Detailed Reporting & Export',
            self::TicketMerging => 'Ticket Merging',
            self::TicketReopening => 'Ticket Re-Opening',
            self::CustomRoles => 'Customized Roles & Permissions',
            self::DepartmentManagement => 'Department Management',
            self::AgentEscalation => 'Agent Tiering & Escalation',
            self::ClientComments => 'Comments & Updates Section (Client-Agents)',
            self::KnowledgeBase => 'Knowledge Base',
            self::CannedResponses => 'Canned Responses',
        };
    }

    /**
     * Get the minimum plan required for this feature.
     */
    public function minimumPlan(): string
    {
        return match ($this) {
            self::AuditLogs,
            self::Billing,
            self::SpamManagement,
            self::ServiceReports,
            self::Attachments,
            self::AgentSchedule,
            self::SlaManagement,
            self::SlaReport,
            self::EmailNotifications,
            self::DetailedReporting => 'business',
            self::TicketMerging,
            self::TicketReopening,
            self::CustomRoles,
            self::DepartmentManagement,
            self::AgentEscalation,
            self::ClientComments,
            self::KnowledgeBase,
            self::CannedResponses => 'enterprise',
        };
    }
}
