# Jeevan Nirman – NBFC Core Banking System

A powerful, high-density, and modern Core Banking Solution (CBS) designed for Non-Banking Financial Companies (NBFCs), Cooperative Societies, and Micro-Finance Institutions. This platform provides a comprehensive suite of tools for member KYC, automated interest calculations, branch-based isolation, and robust financial auditing.

![Status](https://img.shields.io/badge/Status-Stable-emerald)
![License](https://img.shields.io/badge/License-Proprietary-indigo)
![Architecture](https://img.shields.io/badge/Architecture-Branch_Siloed-blue)

---

## 🌟 Key Features

### 🏢 Multi-Branch Architecture (New)
*   **Branch-Based Data Isolation**: Staff members are strictly siloed to their assigned branches. They can only view members, loans, and collections related to their specific base.
*   **Centralized Oversight**: Administrators maintain global access across all branches with the ability to switch views and monitor performance remotely.
*   **Branch Management**: Easily add, update, and manage multiple branch locations and codes.

### 👤 Interactive KYC & Document Suite
*   **Full Identity Stack**: Support for Member Photo and Digital Signature uploads with high-resolution "Zoom" capability.
*   **Document Management**: Upload Aadhar Card, PAN Card, and Cancelled Cheque copies (Up to 2MB).
*   **Live Preview**: Real-time thumbnail generation during enrollment and loan application stages.
*   **Sanction Audit Modal**: A professional, full-screen document viewer for administrators to verify KYC materials before loan approval.

### 🏦 Advanced Loan Engine
*   **Flat & Reducing Interest**: Native support for simple interest and reducing balance EMI models.
*   **Flexible Repayment Cycles**: Configure Monthly, Bi-Weekly, or Weekly collection schedules.
*   **Sanction Queue**: Staff can apply for loans; final "Approval & Issue" is restricted to the Administrative sanction panel.
*   **Automated EMI Scheduler**: Precision calculation of principal and interest components across the loan tenure.

### 🛡️ Audit & Financial Control
*   **Soft Transaction Cancellation**: Revert payment entries with mandatory "Cancellation Reasons" and a full digital audit trail.
*   **Commission Tracking**: Automated calculation of Disbursal and Collection commissions for advisors.
*   **Defaulter Analytics**: Early-warning reports for overdue installments across branches.
*   **Money Received List**: Specialized branch-filtered collection reports for daily reconciliations.

---

## 🚀 Technical stack
*   **Backend**: PHP 8.x (High-Performance mysqli)
*   **Frontend**: Tailwind CSS (Premium Compact Architecture)
*   **Icons**: Phosphor Icons (Industry Standard)
*   **Migration**: Independent Schema Sync Engine (`migrate.php`)

---

## 🛠️ Installation & Setup

1.  **Server Requirements**: 
    *   XAMPP/WAMP/LAMP (PHP 7.4+).
    *   MySQL 5.7+ or MariaDB.
2.  **Database Configuration**:
    *   Import `database.sql` for fresh installations.
    *   Update `includes/db.php` with your connection details.
3.  **Live Server Migration**:
    *   If updating an existing installation, browse to `yoursite.com/migrate.php` once to automatically sync the latest schema (Branch ID columns, KYC paths, Audit tables).
    *   **CRITICAL**: Delete `migrate.php` immediately after execution.
4.  **Permissions**:
    *   Ensure the `/uploads` and `/uploads/documents` directories are writable (0755/0777).

---

## 🛡️ Security & Compliance
*   **Role-Based Access Control (RBAC)**: Distinct permissions for Global Admin and Branch Staff.
*   **Atomic Transactions**: Double-entry accounting principles to ensure ledger integrity.
*   **Data Siloing**: Hardware-level logic in SQL queries using `getBranchWhere()` to prevent staff from accessing cross-branch data.

---
© 2026 Developed for NBFC Core Operations. All rights reserved.
