<?php

namespace Database\Seeders\Catalogue;

/**
 * The default skill vocabulary a workspace is installed with (spec §9–§18).
 *
 * Data only: no logic, no database, so a typo fails the unit test rather than a
 * customer's install. `AiAgentCatalogueInstaller` is the only reader that writes.
 *
 * Every slug is `Str::slug($name)` with no exceptions, so a skill an administrator
 * later re-creates by name collides with the seeded row instead of shadowing it.
 *
 * The spec lists these under department headings and repeats thirteen of them across
 * sections (Root Cause Analysis, Intent Detection, Sentiment Analysis, …). They are
 * collapsed to one row each — §2/§3 require a skill to be reusable across agents and
 * departments, so `skill_category` holds a **functional domain**, never a department
 * name. A department heading in this column would encode exactly the coupling §40
 * forbids, and Root Cause Analysis would need four values for one row.
 */
class SkillCatalogue
{
    /**
     * The filter vocabulary P3's skills index renders, and the authority this file's
     * own categories are checked against. A *filter* list, not a validation rule: an
     * administrator may categorise their own skill however they like.
     *
     * @var list<string>
     */
    public const CATEGORIES = [
        'General & Ticketing',
        'Analysis & Research',
        'Routing & Escalation',
        'Documentation & Communication',
        'HR & People',
        'Payroll & Benefits',
        'Operations & Process',
        'Pre-Sales & Solutioning',
        'Estimation & Costing',
        'Procurement & Vendors',
        'Software Engineering',
        'Databases & Data',
        'Infrastructure & DevOps',
        'Quality & Testing',
        'Security',
        'Hardware & Endpoints',
        'Networking',
        'Sales & Revenue',
        'Customer Experience',
    ];

    /**
     * @var list<array{name: string, slug: string, skill_category: string}>
     */
    public const SKILLS = [
        // General & Ticketing
        ['name' => 'Ticket Classification', 'slug' => 'ticket-classification', 'skill_category' => 'General & Ticketing'],
        ['name' => 'Intent Detection', 'slug' => 'intent-detection', 'skill_category' => 'General & Ticketing'],
        ['name' => 'Request Classification', 'slug' => 'request-classification', 'skill_category' => 'General & Ticketing'],
        ['name' => 'Priority Analysis', 'slug' => 'priority-analysis', 'skill_category' => 'General & Ticketing'],
        ['name' => 'Severity Analysis', 'slug' => 'severity-analysis', 'skill_category' => 'General & Ticketing'],
        ['name' => 'Ticket Summarization', 'slug' => 'ticket-summarization', 'skill_category' => 'General & Ticketing'],
        ['name' => 'Ticket Detail Enhancement', 'slug' => 'ticket-detail-enhancement', 'skill_category' => 'General & Ticketing'],
        ['name' => 'Missing Information Detection', 'slug' => 'missing-information-detection', 'skill_category' => 'General & Ticketing'],
        ['name' => 'Clarification Question Generation', 'slug' => 'clarification-question-generation', 'skill_category' => 'General & Ticketing'],
        ['name' => 'Resolution Recommendation', 'slug' => 'resolution-recommendation', 'skill_category' => 'General & Ticketing'],
        ['name' => 'General Request Analysis', 'slug' => 'general-request-analysis', 'skill_category' => 'General & Ticketing'],

        // Analysis & Research
        ['name' => 'Root Cause Analysis', 'slug' => 'root-cause-analysis', 'skill_category' => 'Analysis & Research'],
        ['name' => 'Attachment Analysis', 'slug' => 'attachment-analysis', 'skill_category' => 'Analysis & Research'],
        ['name' => 'Knowledge Base Search', 'slug' => 'knowledge-base-search', 'skill_category' => 'Analysis & Research'],
        ['name' => 'KPI Analysis', 'slug' => 'kpi-analysis', 'skill_category' => 'Analysis & Research'],
        ['name' => 'Incident Analysis', 'slug' => 'incident-analysis', 'skill_category' => 'Analysis & Research'],
        ['name' => 'General Research', 'slug' => 'general-research', 'skill_category' => 'Analysis & Research'],
        ['name' => 'Information Analysis', 'slug' => 'information-analysis', 'skill_category' => 'Analysis & Research'],
        ['name' => 'Problem Solving', 'slug' => 'problem-solving', 'skill_category' => 'Analysis & Research'],

        // Routing & Escalation
        ['name' => 'Department Routing', 'slug' => 'department-routing', 'skill_category' => 'Routing & Escalation'],
        ['name' => 'Skill Matching', 'slug' => 'skill-matching', 'skill_category' => 'Routing & Escalation'],
        ['name' => 'Agent Routing', 'slug' => 'agent-routing', 'skill_category' => 'Routing & Escalation'],
        ['name' => 'SLA Analysis', 'slug' => 'sla-analysis', 'skill_category' => 'Routing & Escalation'],
        ['name' => 'Escalation Detection', 'slug' => 'escalation-detection', 'skill_category' => 'Routing & Escalation'],
        ['name' => 'Customer Escalation', 'slug' => 'customer-escalation', 'skill_category' => 'Routing & Escalation'],
        ['name' => 'Department Escalation', 'slug' => 'department-escalation', 'skill_category' => 'Routing & Escalation'],
        ['name' => 'Department Classification', 'slug' => 'department-classification', 'skill_category' => 'Routing & Escalation'],
        ['name' => 'Department Recommendation', 'slug' => 'department-recommendation', 'skill_category' => 'Routing & Escalation'],
        ['name' => 'Agent Matching', 'slug' => 'agent-matching', 'skill_category' => 'Routing & Escalation'],
        ['name' => 'Request Routing', 'slug' => 'request-routing', 'skill_category' => 'Routing & Escalation'],

        // Documentation & Communication
        ['name' => 'Response Drafting', 'slug' => 'response-drafting', 'skill_category' => 'Documentation & Communication'],
        ['name' => 'Documentation', 'slug' => 'documentation', 'skill_category' => 'Documentation & Communication'],
        ['name' => 'HR Documentation', 'slug' => 'hr-documentation', 'skill_category' => 'Documentation & Communication'],
        ['name' => 'Memo Drafting', 'slug' => 'memo-drafting', 'skill_category' => 'Documentation & Communication'],
        ['name' => 'HR Letter Drafting', 'slug' => 'hr-letter-drafting', 'skill_category' => 'Documentation & Communication'],
        ['name' => 'Operations Reporting', 'slug' => 'operations-reporting', 'skill_category' => 'Documentation & Communication'],
        ['name' => 'Proposal Writing', 'slug' => 'proposal-writing', 'skill_category' => 'Documentation & Communication'],
        ['name' => 'Scope of Work Preparation', 'slug' => 'scope-of-work-preparation', 'skill_category' => 'Documentation & Communication'],
        ['name' => 'Technical Documentation', 'slug' => 'technical-documentation', 'skill_category' => 'Documentation & Communication'],
        ['name' => 'RFQ Preparation', 'slug' => 'rfq-preparation', 'skill_category' => 'Documentation & Communication'],
        ['name' => 'Purchase Order Assistance', 'slug' => 'purchase-order-assistance', 'skill_category' => 'Documentation & Communication'],
        ['name' => 'Procurement Documentation', 'slug' => 'procurement-documentation', 'skill_category' => 'Documentation & Communication'],
        ['name' => 'Resolution Documentation', 'slug' => 'resolution-documentation', 'skill_category' => 'Documentation & Communication'],
        ['name' => 'Knowledge Base Writing', 'slug' => 'knowledge-base-writing', 'skill_category' => 'Documentation & Communication'],
        ['name' => 'User Guide Writing', 'slug' => 'user-guide-writing', 'skill_category' => 'Documentation & Communication'],
        ['name' => 'Sales Documentation', 'slug' => 'sales-documentation', 'skill_category' => 'Documentation & Communication'],
        ['name' => 'Customer Communication', 'slug' => 'customer-communication', 'skill_category' => 'Documentation & Communication'],
        ['name' => 'Tone Adjustment', 'slug' => 'tone-adjustment', 'skill_category' => 'Documentation & Communication'],
        ['name' => 'Explanation Simplification', 'slug' => 'explanation-simplification', 'skill_category' => 'Documentation & Communication'],

        // HR & People
        ['name' => 'HR Request Classification', 'slug' => 'hr-request-classification', 'skill_category' => 'HR & People'],
        ['name' => 'HR Policy Analysis', 'slug' => 'hr-policy-analysis', 'skill_category' => 'HR & People'],
        ['name' => 'Company Policy Interpretation', 'slug' => 'company-policy-interpretation', 'skill_category' => 'HR & People'],
        ['name' => 'Employee Records', 'slug' => 'employee-records', 'skill_category' => 'HR & People'],
        ['name' => 'Recruitment', 'slug' => 'recruitment', 'skill_category' => 'HR & People'],
        ['name' => 'Applicant Screening', 'slug' => 'applicant-screening', 'skill_category' => 'HR & People'],
        ['name' => 'Job Requirement Analysis', 'slug' => 'job-requirement-analysis', 'skill_category' => 'HR & People'],
        ['name' => 'Interview Preparation', 'slug' => 'interview-preparation', 'skill_category' => 'HR & People'],
        ['name' => 'Employee Relations', 'slug' => 'employee-relations', 'skill_category' => 'HR & People'],

        // Payroll & Benefits
        ['name' => 'Leave Management', 'slug' => 'leave-management', 'skill_category' => 'Payroll & Benefits'],
        ['name' => 'Attendance Analysis', 'slug' => 'attendance-analysis', 'skill_category' => 'Payroll & Benefits'],
        ['name' => 'Overtime Computation', 'slug' => 'overtime-computation', 'skill_category' => 'Payroll & Benefits'],
        ['name' => 'Payroll Analysis', 'slug' => 'payroll-analysis', 'skill_category' => 'Payroll & Benefits'],
        ['name' => 'Payroll Computation', 'slug' => 'payroll-computation', 'skill_category' => 'Payroll & Benefits'],
        ['name' => 'Benefits Administration', 'slug' => 'benefits-administration', 'skill_category' => 'Payroll & Benefits'],

        // Operations & Process
        ['name' => 'Operations Management', 'slug' => 'operations-management', 'skill_category' => 'Operations & Process'],
        ['name' => 'Process Analysis', 'slug' => 'process-analysis', 'skill_category' => 'Operations & Process'],
        ['name' => 'Workflow Analysis', 'slug' => 'workflow-analysis', 'skill_category' => 'Operations & Process'],
        ['name' => 'Process Improvement', 'slug' => 'process-improvement', 'skill_category' => 'Operations & Process'],
        ['name' => 'Bottleneck Detection', 'slug' => 'bottleneck-detection', 'skill_category' => 'Operations & Process'],
        ['name' => 'Service Delivery', 'slug' => 'service-delivery', 'skill_category' => 'Operations & Process'],
        ['name' => 'Operational Monitoring', 'slug' => 'operational-monitoring', 'skill_category' => 'Operations & Process'],
        ['name' => 'SLA Monitoring', 'slug' => 'sla-monitoring', 'skill_category' => 'Operations & Process'],
        ['name' => 'Task Coordination', 'slug' => 'task-coordination', 'skill_category' => 'Operations & Process'],
        ['name' => 'Dependency Analysis', 'slug' => 'dependency-analysis', 'skill_category' => 'Operations & Process'],
        ['name' => 'Resource Coordination', 'slug' => 'resource-coordination', 'skill_category' => 'Operations & Process'],
        ['name' => 'Operational Risk Analysis', 'slug' => 'operational-risk-analysis', 'skill_category' => 'Operations & Process'],

        // Pre-Sales & Solutioning
        ['name' => 'Client Requirement Analysis', 'slug' => 'client-requirement-analysis', 'skill_category' => 'Pre-Sales & Solutioning'],
        ['name' => 'Requirements Gathering', 'slug' => 'requirements-gathering', 'skill_category' => 'Pre-Sales & Solutioning'],
        ['name' => 'Requirement Classification', 'slug' => 'requirement-classification', 'skill_category' => 'Pre-Sales & Solutioning'],
        ['name' => 'Scope Analysis', 'slug' => 'scope-analysis', 'skill_category' => 'Pre-Sales & Solutioning'],
        ['name' => 'Feature Identification', 'slug' => 'feature-identification', 'skill_category' => 'Pre-Sales & Solutioning'],
        ['name' => 'Business Analysis', 'slug' => 'business-analysis', 'skill_category' => 'Pre-Sales & Solutioning'],
        ['name' => 'Solution Design', 'slug' => 'solution-design', 'skill_category' => 'Pre-Sales & Solutioning'],
        ['name' => 'Solution Architecture', 'slug' => 'solution-architecture', 'skill_category' => 'Pre-Sales & Solutioning'],
        ['name' => 'Technical Recommendation', 'slug' => 'technical-recommendation', 'skill_category' => 'Pre-Sales & Solutioning'],
        ['name' => 'Technology Assessment', 'slug' => 'technology-assessment', 'skill_category' => 'Pre-Sales & Solutioning'],

        // Estimation & Costing
        ['name' => 'Effort Estimation', 'slug' => 'effort-estimation', 'skill_category' => 'Estimation & Costing'],
        ['name' => 'Resource Estimation', 'slug' => 'resource-estimation', 'skill_category' => 'Estimation & Costing'],
        ['name' => 'Timeline Estimation', 'slug' => 'timeline-estimation', 'skill_category' => 'Estimation & Costing'],
        ['name' => 'Cost Estimation', 'slug' => 'cost-estimation', 'skill_category' => 'Estimation & Costing'],
        ['name' => 'Price Comparison', 'slug' => 'price-comparison', 'skill_category' => 'Estimation & Costing'],
        ['name' => 'Cost Analysis', 'slug' => 'cost-analysis', 'skill_category' => 'Estimation & Costing'],
        ['name' => 'Budget Analysis', 'slug' => 'budget-analysis', 'skill_category' => 'Estimation & Costing'],
        ['name' => 'Cost Optimization', 'slug' => 'cost-optimization', 'skill_category' => 'Estimation & Costing'],

        // Procurement & Vendors
        ['name' => 'Procurement Request Analysis', 'slug' => 'procurement-request-analysis', 'skill_category' => 'Procurement & Vendors'],
        ['name' => 'Purchase Requirement Analysis', 'slug' => 'purchase-requirement-analysis', 'skill_category' => 'Procurement & Vendors'],
        ['name' => 'Technical Specification Analysis', 'slug' => 'technical-specification-analysis', 'skill_category' => 'Procurement & Vendors'],
        ['name' => 'Specification Writing', 'slug' => 'specification-writing', 'skill_category' => 'Procurement & Vendors'],
        ['name' => 'Specification Validation', 'slug' => 'specification-validation', 'skill_category' => 'Procurement & Vendors'],
        ['name' => 'Product Research', 'slug' => 'product-research', 'skill_category' => 'Procurement & Vendors'],
        ['name' => 'Vendor Evaluation', 'slug' => 'vendor-evaluation', 'skill_category' => 'Procurement & Vendors'],
        ['name' => 'Vendor Comparison', 'slug' => 'vendor-comparison', 'skill_category' => 'Procurement & Vendors'],
        ['name' => 'Product Comparison', 'slug' => 'product-comparison', 'skill_category' => 'Procurement & Vendors'],
        ['name' => 'Specification Matching', 'slug' => 'specification-matching', 'skill_category' => 'Procurement & Vendors'],
        ['name' => 'Technical Specification Matching', 'slug' => 'technical-specification-matching', 'skill_category' => 'Procurement & Vendors'],

        // Software Engineering
        ['name' => 'Software Ticket Classification', 'slug' => 'software-ticket-classification', 'skill_category' => 'Software Engineering'],
        ['name' => 'Bug Classification', 'slug' => 'bug-classification', 'skill_category' => 'Software Engineering'],
        ['name' => 'Feature Request Analysis', 'slug' => 'feature-request-analysis', 'skill_category' => 'Software Engineering'],
        ['name' => 'Application Troubleshooting', 'slug' => 'application-troubleshooting', 'skill_category' => 'Software Engineering'],
        ['name' => 'Software Engineering', 'slug' => 'software-engineering', 'skill_category' => 'Software Engineering'],
        ['name' => 'Laravel', 'slug' => 'laravel', 'skill_category' => 'Software Engineering'],
        ['name' => 'PHP', 'slug' => 'php', 'skill_category' => 'Software Engineering'],
        ['name' => 'Livewire', 'slug' => 'livewire', 'skill_category' => 'Software Engineering'],
        ['name' => 'JavaScript', 'slug' => 'javascript', 'skill_category' => 'Software Engineering'],
        ['name' => 'Tailwind CSS', 'slug' => 'tailwind-css', 'skill_category' => 'Software Engineering'],
        ['name' => 'HTML', 'slug' => 'html', 'skill_category' => 'Software Engineering'],
        ['name' => 'CSS', 'slug' => 'css', 'skill_category' => 'Software Engineering'],
        ['name' => 'REST API', 'slug' => 'rest-api', 'skill_category' => 'Software Engineering'],
        ['name' => 'API Integration', 'slug' => 'api-integration', 'skill_category' => 'Software Engineering'],
        ['name' => 'Software Architecture', 'slug' => 'software-architecture', 'skill_category' => 'Software Engineering'],
        ['name' => 'System Design', 'slug' => 'system-design', 'skill_category' => 'Software Engineering'],
        ['name' => 'Backend Development', 'slug' => 'backend-development', 'skill_category' => 'Software Engineering'],
        ['name' => 'Frontend Development', 'slug' => 'frontend-development', 'skill_category' => 'Software Engineering'],
        ['name' => 'Full Stack Development', 'slug' => 'full-stack-development', 'skill_category' => 'Software Engineering'],
        ['name' => 'Debugging', 'slug' => 'debugging', 'skill_category' => 'Software Engineering'],
        ['name' => 'Log Analysis', 'slug' => 'log-analysis', 'skill_category' => 'Software Engineering'],
        ['name' => 'Stack Trace Analysis', 'slug' => 'stack-trace-analysis', 'skill_category' => 'Software Engineering'],

        // Databases & Data
        ['name' => 'MySQL', 'slug' => 'mysql', 'skill_category' => 'Databases & Data'],
        ['name' => 'SQL', 'slug' => 'sql', 'skill_category' => 'Databases & Data'],
        ['name' => 'Database Design', 'slug' => 'database-design', 'skill_category' => 'Databases & Data'],
        ['name' => 'Database Troubleshooting', 'slug' => 'database-troubleshooting', 'skill_category' => 'Databases & Data'],
        ['name' => 'Query Optimization', 'slug' => 'query-optimization', 'skill_category' => 'Databases & Data'],
        ['name' => 'Data Integrity', 'slug' => 'data-integrity', 'skill_category' => 'Databases & Data'],

        // Infrastructure & DevOps
        ['name' => 'Docker', 'slug' => 'docker', 'skill_category' => 'Infrastructure & DevOps'],
        ['name' => 'Linux', 'slug' => 'linux', 'skill_category' => 'Infrastructure & DevOps'],
        ['name' => 'Ubuntu', 'slug' => 'ubuntu', 'skill_category' => 'Infrastructure & DevOps'],
        ['name' => 'Nginx', 'slug' => 'nginx', 'skill_category' => 'Infrastructure & DevOps'],
        ['name' => 'Cloudflare', 'slug' => 'cloudflare', 'skill_category' => 'Infrastructure & DevOps'],
        ['name' => 'Cloudflare Tunnel', 'slug' => 'cloudflare-tunnel', 'skill_category' => 'Infrastructure & DevOps'],
        ['name' => 'Git', 'slug' => 'git', 'skill_category' => 'Infrastructure & DevOps'],
        ['name' => 'GitHub', 'slug' => 'github', 'skill_category' => 'Infrastructure & DevOps'],
        ['name' => 'CI/CD', 'slug' => 'cicd', 'skill_category' => 'Infrastructure & DevOps'],
        ['name' => 'Deployment', 'slug' => 'deployment', 'skill_category' => 'Infrastructure & DevOps'],
        ['name' => 'Server Troubleshooting', 'slug' => 'server-troubleshooting', 'skill_category' => 'Infrastructure & DevOps'],
        ['name' => 'Queue Management', 'slug' => 'queue-management', 'skill_category' => 'Infrastructure & DevOps'],
        ['name' => 'Redis', 'slug' => 'redis', 'skill_category' => 'Infrastructure & DevOps'],
        ['name' => 'Laravel Horizon', 'slug' => 'laravel-horizon', 'skill_category' => 'Infrastructure & DevOps'],
        ['name' => 'Server Hardware', 'slug' => 'server-hardware', 'skill_category' => 'Infrastructure & DevOps'],
        ['name' => 'Storage', 'slug' => 'storage', 'skill_category' => 'Infrastructure & DevOps'],
        ['name' => 'RAID', 'slug' => 'raid', 'skill_category' => 'Infrastructure & DevOps'],
        ['name' => 'UPS', 'slug' => 'ups', 'skill_category' => 'Infrastructure & DevOps'],
        ['name' => 'Virtualization', 'slug' => 'virtualization', 'skill_category' => 'Infrastructure & DevOps'],
        ['name' => 'Infrastructure Troubleshooting', 'slug' => 'infrastructure-troubleshooting', 'skill_category' => 'Infrastructure & DevOps'],

        // Quality & Testing
        ['name' => 'Bug Reproduction', 'slug' => 'bug-reproduction', 'skill_category' => 'Quality & Testing'],
        ['name' => 'Quality Assurance', 'slug' => 'quality-assurance', 'skill_category' => 'Quality & Testing'],
        ['name' => 'Test Case Generation', 'slug' => 'test-case-generation', 'skill_category' => 'Quality & Testing'],
        ['name' => 'Test Scenario Generation', 'slug' => 'test-scenario-generation', 'skill_category' => 'Quality & Testing'],
        ['name' => 'Regression Testing', 'slug' => 'regression-testing', 'skill_category' => 'Quality & Testing'],
        ['name' => 'Functional Testing', 'slug' => 'functional-testing', 'skill_category' => 'Quality & Testing'],
        ['name' => 'Integration Testing', 'slug' => 'integration-testing', 'skill_category' => 'Quality & Testing'],
        ['name' => 'Acceptance Criteria Analysis', 'slug' => 'acceptance-criteria-analysis', 'skill_category' => 'Quality & Testing'],
        ['name' => 'Bug Verification', 'slug' => 'bug-verification', 'skill_category' => 'Quality & Testing'],

        // Security
        ['name' => 'Application Security', 'slug' => 'application-security', 'skill_category' => 'Security'],
        ['name' => 'Authentication Analysis', 'slug' => 'authentication-analysis', 'skill_category' => 'Security'],
        ['name' => 'Authorization Analysis', 'slug' => 'authorization-analysis', 'skill_category' => 'Security'],
        ['name' => 'Vulnerability Analysis', 'slug' => 'vulnerability-analysis', 'skill_category' => 'Security'],

        // Hardware & Endpoints
        ['name' => 'Hardware Ticket Classification', 'slug' => 'hardware-ticket-classification', 'skill_category' => 'Hardware & Endpoints'],
        ['name' => 'Hardware Troubleshooting', 'slug' => 'hardware-troubleshooting', 'skill_category' => 'Hardware & Endpoints'],
        ['name' => 'Desktop Troubleshooting', 'slug' => 'desktop-troubleshooting', 'skill_category' => 'Hardware & Endpoints'],
        ['name' => 'Laptop Troubleshooting', 'slug' => 'laptop-troubleshooting', 'skill_category' => 'Hardware & Endpoints'],
        ['name' => 'Peripheral Troubleshooting', 'slug' => 'peripheral-troubleshooting', 'skill_category' => 'Hardware & Endpoints'],
        ['name' => 'Printer Troubleshooting', 'slug' => 'printer-troubleshooting', 'skill_category' => 'Hardware & Endpoints'],
        ['name' => 'Component Diagnostics', 'slug' => 'component-diagnostics', 'skill_category' => 'Hardware & Endpoints'],
        ['name' => 'Hardware Diagnostics', 'slug' => 'hardware-diagnostics', 'skill_category' => 'Hardware & Endpoints'],
        ['name' => 'Component Failure Analysis', 'slug' => 'component-failure-analysis', 'skill_category' => 'Hardware & Endpoints'],
        ['name' => 'Hardware Compatibility', 'slug' => 'hardware-compatibility', 'skill_category' => 'Hardware & Endpoints'],
        ['name' => 'Hardware Upgrade Analysis', 'slug' => 'hardware-upgrade-analysis', 'skill_category' => 'Hardware & Endpoints'],
        ['name' => 'Replacement Recommendation', 'slug' => 'replacement-recommendation', 'skill_category' => 'Hardware & Endpoints'],

        // Networking
        ['name' => 'Network Troubleshooting', 'slug' => 'network-troubleshooting', 'skill_category' => 'Networking'],
        ['name' => 'LAN', 'slug' => 'lan', 'skill_category' => 'Networking'],
        ['name' => 'WAN', 'slug' => 'wan', 'skill_category' => 'Networking'],
        ['name' => 'Wi-Fi', 'slug' => 'wi-fi', 'skill_category' => 'Networking'],
        ['name' => 'Router Configuration', 'slug' => 'router-configuration', 'skill_category' => 'Networking'],
        ['name' => 'Switch Configuration', 'slug' => 'switch-configuration', 'skill_category' => 'Networking'],
        ['name' => 'DNS Troubleshooting', 'slug' => 'dns-troubleshooting', 'skill_category' => 'Networking'],
        ['name' => 'IP Configuration', 'slug' => 'ip-configuration', 'skill_category' => 'Networking'],
        ['name' => 'Network Diagnostics', 'slug' => 'network-diagnostics', 'skill_category' => 'Networking'],

        // Sales & Revenue
        ['name' => 'Sales Inquiry Classification', 'slug' => 'sales-inquiry-classification', 'skill_category' => 'Sales & Revenue'],
        ['name' => 'Lead Classification', 'slug' => 'lead-classification', 'skill_category' => 'Sales & Revenue'],
        ['name' => 'Lead Qualification', 'slug' => 'lead-qualification', 'skill_category' => 'Sales & Revenue'],
        ['name' => 'Customer Requirement Analysis', 'slug' => 'customer-requirement-analysis', 'skill_category' => 'Sales & Revenue'],
        ['name' => 'Opportunity Analysis', 'slug' => 'opportunity-analysis', 'skill_category' => 'Sales & Revenue'],
        ['name' => 'Product Knowledge', 'slug' => 'product-knowledge', 'skill_category' => 'Sales & Revenue'],
        ['name' => 'Product Matching', 'slug' => 'product-matching', 'skill_category' => 'Sales & Revenue'],
        ['name' => 'Feature Matching', 'slug' => 'feature-matching', 'skill_category' => 'Sales & Revenue'],
        ['name' => 'Solution Recommendation', 'slug' => 'solution-recommendation', 'skill_category' => 'Sales & Revenue'],
        ['name' => 'Quotation Preparation', 'slug' => 'quotation-preparation', 'skill_category' => 'Sales & Revenue'],
        ['name' => 'Pricing Analysis', 'slug' => 'pricing-analysis', 'skill_category' => 'Sales & Revenue'],
        ['name' => 'Package Analysis', 'slug' => 'package-analysis', 'skill_category' => 'Sales & Revenue'],
        ['name' => 'Sales Communication', 'slug' => 'sales-communication', 'skill_category' => 'Sales & Revenue'],
        ['name' => 'Sales Follow-Up', 'slug' => 'sales-follow-up', 'skill_category' => 'Sales & Revenue'],
        ['name' => 'Next Action Recommendation', 'slug' => 'next-action-recommendation', 'skill_category' => 'Sales & Revenue'],
        ['name' => 'Customer Objection Analysis', 'slug' => 'customer-objection-analysis', 'skill_category' => 'Sales & Revenue'],

        // Customer Experience
        ['name' => 'Sentiment Analysis', 'slug' => 'sentiment-analysis', 'skill_category' => 'Customer Experience'],
        ['name' => 'Customer Request Classification', 'slug' => 'customer-request-classification', 'skill_category' => 'Customer Experience'],
        ['name' => 'Customer Intent Detection', 'slug' => 'customer-intent-detection', 'skill_category' => 'Customer Experience'],
        ['name' => 'Customer Support', 'slug' => 'customer-support', 'skill_category' => 'Customer Experience'],
        ['name' => 'General Troubleshooting', 'slug' => 'general-troubleshooting', 'skill_category' => 'Customer Experience'],
        ['name' => 'FAQ Resolution', 'slug' => 'faq-resolution', 'skill_category' => 'Customer Experience'],
        ['name' => 'Product Guidance', 'slug' => 'product-guidance', 'skill_category' => 'Customer Experience'],
        ['name' => 'Complaint Analysis', 'slug' => 'complaint-analysis', 'skill_category' => 'Customer Experience'],
        ['name' => 'Complaint Resolution', 'slug' => 'complaint-resolution', 'skill_category' => 'Customer Experience'],
        ['name' => 'Conflict Resolution', 'slug' => 'conflict-resolution', 'skill_category' => 'Customer Experience'],
        ['name' => 'Customer Follow-Up', 'slug' => 'customer-follow-up', 'skill_category' => 'Customer Experience'],
    ];

    /**
     * @return list<array{name: string, slug: string, skill_category: string}>
     */
    public static function all(): array
    {
        return self::SKILLS;
    }

    /**
     * @return array{name: string, slug: string, skill_category: string}|null
     */
    public static function bySlug(string $slug): ?array
    {
        foreach (self::SKILLS as $skill) {
            if ($skill['slug'] === $slug) {
                return $skill;
            }
        }

        return null;
    }
}
