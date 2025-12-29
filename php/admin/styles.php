<?php
// ============================================
// 迴響電競訂單後台 - CSS 樣式
// php/admin/styles.php
// ============================================
?>
<style>
body {
    cursor: auto !important;
    padding: 0;
    display: block;
    min-height: 100vh;
}
body::before, body::after {
    opacity: 0.3;
}
.admin-wrapper {
    max-width: 1400px;
    margin: 0 auto;
    padding: 30px;
}
.admin-nav {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--border-color);
}
.admin-title {
    font-family: 'Orbitron', sans-serif;
    font-size: 28px;
    color: var(--text-primary);
    letter-spacing: 3px;
}
.logout-btn {
    background: rgba(255, 68, 102, 0.2);
    border: 1px solid var(--error-color);
    color: var(--error-color);
    padding: 10px 20px;
    border-radius: 8px;
    cursor: pointer;
    font-family: 'Rajdhani', sans-serif;
    text-decoration: none;
    transition: all 0.3s ease;
}
.logout-btn:hover {
    background: rgba(255, 68, 102, 0.3);
}
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}
.stat-card {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: 15px;
    padding: 25px;
    text-align: center;
    transition: all 0.3s ease;
}
.stat-card:hover {
    border-color: var(--primary-color);
    box-shadow: 0 0 20px rgba(0, 212, 255, 0.2);
}
.stat-value {
    font-family: 'Orbitron', sans-serif;
    font-size: 36px;
    color: var(--primary-color);
    margin-bottom: 8px;
}
.stat-label {
    color: var(--text-muted);
    font-size: 14px;
    text-transform: uppercase;
    letter-spacing: 1px;
}
.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}
.section-title {
    font-family: 'Orbitron', sans-serif;
    font-size: 18px;
    color: var(--text-primary);
    margin: 0;
    padding-left: 15px;
    border-left: 3px solid var(--primary-color);
}
.table-container {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: 15px;
    overflow: hidden;
    margin-bottom: 30px;
}
.data-table {
    width: 100%;
    border-collapse: collapse;
}
.data-table th,
.data-table td {
    padding: 15px;
    text-align: left;
    border-bottom: 1px solid var(--border-color);
}
.data-table th {
    background: rgba(0, 212, 255, 0.1);
    color: var(--primary-color);
    font-family: 'Orbitron', sans-serif;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 1px;
    white-space: nowrap;
}
.data-table tr:hover {
    background: rgba(0, 212, 255, 0.05);
}
.data-table td {
    font-family: 'Space Mono', monospace;
    font-size: 13px;
    color: var(--text-secondary);
}
.badge {
    display: inline-block;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}
.badge-success {
    background: rgba(0, 255, 136, 0.2);
    color: var(--success-color);
}
.badge-pending {
    background: rgba(255, 170, 0, 0.2);
    color: var(--warning-color);
}
.badge-credit {
    background: rgba(0, 212, 255, 0.2);
    color: var(--primary-color);
}
.badge-atm {
    background: rgba(0, 255, 204, 0.2);
    color: var(--accent-color);
}
.badge-store {
    background: rgba(255, 170, 0, 0.2);
    color: var(--warning-color);
}
.amount-cell {
    color: var(--success-color) !important;
    font-weight: 600;
}
.pagination {
    display: flex;
    justify-content: center;
    gap: 10px;
    margin-top: 20px;
}
.page-btn {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    color: var(--text-secondary);
    padding: 10px 15px;
    border-radius: 8px;
    cursor: pointer;
    font-family: 'Space Mono', monospace;
    text-decoration: none;
    transition: all 0.3s ease;
}
.page-btn:hover, .page-btn.active {
    border-color: var(--primary-color);
    color: var(--primary-color);
    background: rgba(0, 212, 255, 0.1);
}
.empty-state {
    text-align: center;
    padding: 50px;
    color: var(--text-muted);
}
.empty-state-icon {
    font-size: 48px;
    margin-bottom: 15px;
}
.refresh-btn, .clear-pending-btn, .export-btn {
    background: rgba(0, 212, 255, 0.2);
    border: 1px solid var(--primary-color);
    color: var(--primary-color);
    padding: 10px 20px;
    border-radius: 8px;
    cursor: pointer;
    font-family: 'Rajdhani', sans-serif;
    font-weight: 600;
    transition: all 0.3s ease;
}
.refresh-btn {
    margin-left: 15px;
}
.refresh-btn:hover, .clear-pending-btn:hover, .export-btn:hover {
    background: rgba(0, 212, 255, 0.3);
    transform: translateY(-2px);
}
.delete-btn {
    background: rgba(255, 68, 102, 0.2);
    border: 1px solid var(--error-color);
    color: var(--error-color);
    padding: 5px 10px;
    border-radius: 5px;
    cursor: pointer;
    font-size: 14px;
    transition: all 0.3s ease;
}
.delete-btn:hover {
    background: rgba(255, 68, 102, 0.3);
}
.modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.7);
    z-index: 9998;
    backdrop-filter: blur(5px);
}
.modal {
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: 20px;
    padding: 30px;
    z-index: 9999;
    max-width: 400px;
    width: 90%;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
}
.modal-title {
    font-family: 'Orbitron', sans-serif;
    color: var(--primary-color);
    margin-bottom: 20px;
    font-size: 20px;
}
.modal-options {
    margin: 20px 0;
}
.modal-options label {
    display: block;
    padding: 12px;
    margin: 8px 0;
    background: rgba(0, 212, 255, 0.05);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
}
.modal-options label:hover {
    background: rgba(0, 212, 255, 0.1);
    border-color: var(--primary-color);
}
.modal-options input[type="radio"] {
    margin-right: 10px;
}
.modal-buttons {
    display: flex;
    gap: 10px;
    margin-top: 20px;
}
.modal-btn {
    flex: 1;
    padding: 12px;
    border-radius: 8px;
    border: 1px solid;
    cursor: pointer;
    font-family: 'Rajdhani', sans-serif;
    font-weight: 600;
    transition: all 0.3s ease;
}
.modal-btn-primary {
    background: rgba(0, 212, 255, 0.2);
    border-color: var(--primary-color);
    color: var(--primary-color);
}
.modal-btn-primary:hover {
    background: rgba(0, 212, 255, 0.3);
}
.modal-btn-cancel {
    background: rgba(255, 68, 102, 0.2);
    border-color: var(--error-color);
    color: var(--error-color);
}
.modal-btn-cancel:hover {
    background: rgba(255, 68, 102, 0.3);
}
.toast {
    position: fixed;
    top: 20px;
    right: 20px;
    padding: 15px 25px;
    border-radius: 10px;
    color: white;
    font-family: 'Rajdhani', sans-serif;
    font-weight: 600;
    z-index: 10000;
    animation: slideIn 0.3s ease;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
}
.toast-success {
    background: linear-gradient(135deg, rgba(0, 255, 136, 0.95), rgba(0, 200, 100, 0.95));
}
.toast-error {
    background: linear-gradient(135deg, rgba(255, 68, 102, 0.95), rgba(200, 50, 80, 0.95));
}
@keyframes slideIn {
    from { transform: translateX(400px); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}

@media screen and (max-width: 768px) {
    .admin-wrapper {
        padding: 15px;
    }
    .table-container {
        overflow-x: auto;
    }
    .data-table th,
    .data-table td {
        padding: 10px;
        font-size: 11px;
    }
    .section-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
}
</style>
