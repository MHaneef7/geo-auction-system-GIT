"""
Script to generate Geo-Auction_Complete_Project_Documentation.docx
Complete technical, architectural, setup, and testing documentation
Written in clean English with Roman Urdu explanatory callouts.
"""

import os
import sys
from docx import Document
from docx.shared import Inches, Pt, RGBColor
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.enum.table import WD_TABLE_ALIGNMENT, WD_ALIGN_VERTICAL
from docx.oxml import OxmlElement, parse_xml
from docx.oxml.ns import nsdecls, qn

def set_cell_background(cell, fill_hex):
    """Set background color of a table cell."""
    tcPr = cell._tc.get_or_add_tcPr()
    shd = parse_xml(f'<w:shd {nsdecls("w")} w:fill="{fill_hex}"/>')
    tcPr.append(shd)

def set_cell_margins(cell, top=100, bottom=100, left=150, right=150):
    """Set inner padding for table cell."""
    tcPr = cell._tc.get_or_add_tcPr()
    tcMar = parse_xml(
        f'<w:tcMar {nsdecls("w")}>'
        f'<w:top w:w="{top}" w:type="dxa"/>'
        f'<w:bottom w:w="{bottom}" w:type="dxa"/>'
        f'<w:left w:w="{left}" w:type="dxa"/>'
        f'<w:right w:w="{right}" w:type="dxa"/>'
        f'</w:tcMar>'
    )
    tcPr.append(tcMar)

def add_callout(doc, title, text, is_urdu=True):
    """Creates a callout box with a colored left border and light shading."""
    tbl = doc.add_table(rows=1, cols=1)
    tbl.alignment = WD_TABLE_ALIGNMENT.CENTER
    tbl.autofit = False
    
    cell = tbl.cell(0, 0)
    cell.width = Inches(6.5)
    
    bg_color = "F0FDF4" if is_urdu else "F8FAFC"
    border_color = "047857" if is_urdu else "3B82F6"
    prefix = "🇵🇰 Roman Urdu Wazahat (وضاحت): " if is_urdu else "💡 Note / Best Practice: "
    
    set_cell_background(cell, bg_color)
    set_cell_margins(cell, top=120, bottom=120, left=180, right=150)
    
    # Left border styling
    tcPr = cell._tc.get_or_add_tcPr()
    tcBorders = parse_xml(
        f'<w:tcBorders {nsdecls("w")}>'
        f'<w:top w:val="none"/>'
        f'<w:left w:val="single" w:sz="24" w:space="0" w:color="{border_color}"/>'
        f'<w:bottom w:val="none"/>'
        f'<w:right w:val="none"/>'
        f'</w:tcBorders>'
    )
    tcPr.append(tcBorders)
    
    p = cell.paragraphs[0]
    p.paragraph_format.space_before = Pt(2)
    p.paragraph_format.space_after = Pt(2)
    
    r_title = p.add_run(prefix + title + "\n")
    r_title.bold = True
    r_title.font.name = "Arial"
    r_title.font.size = Pt(10)
    r_title.font.color.rgb = RGBColor(4, 120, 87) if is_urdu else RGBColor(37, 99, 235)
    
    r_text = p.add_run(text)
    r_text.font.name = "Arial"
    r_text.font.size = Pt(9.5)
    r_text.font.italic = True if is_urdu else False
    r_text.font.color.rgb = RGBColor(30, 41, 59)
    
    # Add spacing after table
    sp = doc.add_paragraph()
    sp.paragraph_format.space_before = Pt(0)
    sp.paragraph_format.space_after = Pt(4)

def add_code_block(doc, code_str, language="SQL / PHP"):
    """Adds a formatted code snippet box."""
    tbl = doc.add_table(rows=1, cols=1)
    tbl.alignment = WD_TABLE_ALIGNMENT.CENTER
    cell = tbl.cell(0, 0)
    cell.width = Inches(6.5)
    
    set_cell_background(cell, "0F172A") # slate-900 dark background
    set_cell_margins(cell, top=120, bottom=120, left=150, right=150)
    
    p = cell.paragraphs[0]
    p.paragraph_format.space_before = Pt(0)
    p.paragraph_format.space_after = Pt(0)
    
    r_lang = p.add_run(f"// {language}\n")
    r_lang.font.name = "Consolas"
    r_lang.font.size = Pt(8.5)
    r_lang.font.color.rgb = RGBColor(148, 163, 184) # slate-400
    
    r_code = p.add_run(code_str)
    r_code.font.name = "Consolas"
    r_code.font.size = Pt(9)
    r_code.font.color.rgb = RGBColor(248, 250, 252) # slate-50
    
    sp = doc.add_paragraph()
    sp.paragraph_format.space_before = Pt(0)
    sp.paragraph_format.space_after = Pt(4)

def format_heading(p, text, level=1):
    """Format headings with Geo-Auction branding."""
    p.paragraph_format.keep_with_next = True
    r = p.add_run(text)
    r.font.name = "Arial"
    r.bold = True
    
    if level == 1:
        p.paragraph_format.space_before = Pt(18)
        p.paragraph_format.space_after = Pt(6)
        r.font.size = Pt(18)
        r.font.color.rgb = RGBColor(4, 120, 87) # Theme Emerald Green
    elif level == 2:
        p.paragraph_format.space_before = Pt(14)
        p.paragraph_format.space_after = Pt(4)
        r.font.size = Pt(14)
        r.font.color.rgb = RGBColor(15, 23, 42) # Slate 900
    elif level == 3:
        p.paragraph_format.space_before = Pt(10)
        p.paragraph_format.space_after = Pt(3)
        r.font.size = Pt(11.5)
        r.font.color.rgb = RGBColor(51, 65, 85) # Slate 700

def create_table(doc, headers, data, col_widths=None):
    """Creates a stylized data table with header and alternate row shading."""
    tbl = doc.add_table(rows=len(data) + 1, cols=len(headers))
    tbl.alignment = WD_TABLE_ALIGNMENT.CENTER
    tbl.autofit = False
    
    # Header row
    hdr_cells = tbl.rows[0].cells
    for i, header_text in enumerate(headers):
        hdr_cells[i].text = header_text
        set_cell_background(hdr_cells[i], "047857")
        set_cell_margins(hdr_cells[i], top=100, bottom=100, left=120, right=120)
        p = hdr_cells[i].paragraphs[0]
        p.alignment = WD_ALIGN_PARAGRAPH.LEFT
        for run in p.runs:
            run.font.name = "Arial"
            run.font.size = Pt(9.5)
            run.font.bold = True
            run.font.color.rgb = RGBColor(255, 255, 255)
            
    # Data rows
    for row_idx, row_data in enumerate(data):
        row_cells = tbl.rows[row_idx + 1].cells
        bg_color = "F8FAFC" if (row_idx % 2 == 1) else "FFFFFF"
        for col_idx, cell_value in enumerate(row_data):
            row_cells[col_idx].text = str(cell_value)
            set_cell_background(row_cells[col_idx], bg_color)
            set_cell_margins(row_cells[col_idx], top=80, bottom=80, left=120, right=120)
            p = row_cells[col_idx].paragraphs[0]
            p.alignment = WD_ALIGN_PARAGRAPH.LEFT
            for run in p.runs:
                run.font.name = "Arial"
                run.font.size = Pt(9)
                run.font.color.rgb = RGBColor(30, 41, 59)
                
    if col_widths:
        for row in tbl.rows:
            for idx, width in enumerate(col_widths):
                row.cells[idx].width = Inches(width)
                
    sp = doc.add_paragraph()
    sp.paragraph_format.space_before = Pt(0)
    sp.paragraph_format.space_after = Pt(6)

def add_body_p(doc, text):
    """Add standard body paragraph."""
    p = doc.add_paragraph()
    p.paragraph_format.space_before = Pt(0)
    p.paragraph_format.space_after = Pt(4)
    p.paragraph_format.line_spacing = 1.15
    r = p.add_run(text)
    r.font.name = "Arial"
    r.font.size = Pt(10)
    r.font.color.rgb = RGBColor(51, 65, 85)
    return p

def add_bullet_p(doc, bold_prefix, text):
    """Add bullet point with bold lead."""
    p = doc.add_paragraph(style='List Bullet')
    p.paragraph_format.space_before = Pt(0)
    p.paragraph_format.space_after = Pt(3)
    p.paragraph_format.line_spacing = 1.15
    
    r_bold = p.add_run(bold_prefix)
    r_bold.font.name = "Arial"
    r_bold.font.size = Pt(10)
    r_bold.bold = True
    r_bold.font.color.rgb = RGBColor(15, 23, 42)
    
    r_text = p.add_run(text)
    r_text.font.name = "Arial"
    r_text.font.size = Pt(10)
    r_text.font.color.rgb = RGBColor(51, 65, 85)
    return p

def add_placeholder_box(doc, caption_text):
    """Creates an attractive screenshot placeholder box."""
    tbl = doc.add_table(rows=1, cols=1)
    tbl.alignment = WD_TABLE_ALIGNMENT.CENTER
    cell = tbl.cell(0, 0)
    cell.width = Inches(6.5)
    
    set_cell_background(cell, "F1F5F9")
    set_cell_margins(cell, top=200, bottom=200, left=150, right=150)
    
    # dashed border
    tcPr = cell._tc.get_or_add_tcPr()
    tcBorders = parse_xml(
        f'<w:tcBorders {nsdecls("w")}>'
        f'<w:top w:val="dashed" w:sz="12" w:space="0" w:color="94A3B8"/>'
        f'<w:left w:val="dashed" w:sz="12" w:space="0" w:color="94A3B8"/>'
        f'<w:bottom w:val="dashed" w:sz="12" w:space="0" w:color="94A3B8"/>'
        f'<w:right w:val="dashed" w:sz="12" w:space="0" w:color="94A3B8"/>'
        f'</w:tcBorders>'
    )
    tcPr.append(tcBorders)
    
    p = cell.paragraphs[0]
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    
    r_icon = p.add_run("📷 [SCREENSHOT PLACEHOLDER]\n")
    r_icon.font.name = "Arial"
    r_icon.font.size = Pt(11)
    r_icon.font.bold = True
    r_icon.font.color.rgb = RGBColor(71, 85, 105)
    
    r_cap = p.add_run(caption_text)
    r_cap.font.name = "Arial"
    r_cap.font.size = Pt(9.5)
    r_cap.font.italic = True
    r_cap.font.color.rgb = RGBColor(100, 116, 139)
    
    sp = doc.add_paragraph()
    sp.paragraph_format.space_before = Pt(0)
    sp.paragraph_format.space_after = Pt(6)

print("Building Geo-Auction Complete Project Documentation...")
doc = Document()

# Page Margins
sections = doc.sections
for s in sections:
    s.top_margin = Inches(1.0)
    s.bottom_margin = Inches(1.0)
    s.left_margin = Inches(1.0)
    s.right_margin = Inches(1.0)

# ==============================================================================
# COVER PAGE / TITLE
# ==============================================================================
p_cov_space = doc.add_paragraph()
p_cov_space.paragraph_format.space_before = Pt(40)

p_title = doc.add_paragraph()
p_title.alignment = WD_ALIGN_PARAGRAPH.CENTER
r_t = p_title.add_run("GEO-AUCTION SYSTEM")
r_t.font.name = "Arial"
r_t.font.size = Pt(30)
r_t.font.bold = True
r_t.font.color.rgb = RGBColor(4, 120, 87)

p_sub = doc.add_paragraph()
p_sub.alignment = WD_ALIGN_PARAGRAPH.CENTER
p_sub.paragraph_format.space_after = Pt(24)
r_s = p_sub.add_run("Web-Based Real-Time Auction & Marketplace Management System\nComplete Technical, Architectural, Implementation & Testing Manual")
r_s.font.name = "Arial"
r_s.font.size = Pt(13)
r_s.font.color.rgb = RGBColor(71, 85, 105)

# Decorative Horizontal Divider
p_div = doc.add_paragraph()
p_div.alignment = WD_ALIGN_PARAGRAPH.CENTER
p_div.paragraph_format.space_after = Pt(36)
r_d = p_div.add_run("―" * 45)
r_d.font.color.rgb = RGBColor(4, 120, 87)

# Metadata Table
meta_headers = ["Project Specification", "System Details"]
meta_rows = [
    ["Platform Name", "Geo-Auction (Web-Based Auction Management Platform)"],
    ["Version & Status", "Version 2.4.0 — Production Ready & Fully Audited"],
    ["Primary Architecture", "Object-Oriented PHP 8.x, PDO Prepared Queries, MySQL 8 / MariaDB"],
    ["Frontend Stack", "Bootstrap 5, Vanilla CSS3 Custom Tokens, FontAwesome 6, Chart.js"],
    ["Payment Architecture", "Static / Manual Payment Verification (Bank Alfalah, JazzCash, Easypaisa) + Escrow Wallet"],
    ["Concurrency Engine", "Atomic Row Locking (SELECT ... FOR UPDATE) & Anti-Sniping Extension"],
    ["Operating Environment", "XAMPP 8.2+ on Windows / Apache 2.4 / MariaDB 10.4"],
    ["Documentation Scope", "Complete 24-Section Implementation, Deployment & User Guide"]
]
create_table(doc, meta_headers, meta_rows, [2.2, 4.3])

add_callout(doc, "Mukammal Dastaweez (Complete Manual Overview)",
    "Yeh documentation Geo-Auction system ke tamam technical, database, frontend aur backend features ko explain karti hai. Is mein har module ka asal code, database schema, payment verification workflow, aur step-by-step testing instructions shamil hain taake koi bhi developer ya tester is system ko ba-asani chala sake aur verify kar sake.",
    is_urdu=True
)

doc.add_page_break()

# ==============================================================================
# SECTION 1: PROJECT INTRODUCTION AND OBJECTIVES
# ==============================================================================
p = doc.add_paragraph()
format_heading(p, "1. Project Introduction and Objectives", level=1)

add_body_p(doc, 
    "Geo-Auction is a high-performance, web-based auction and marketplace platform specifically engineered for dynamic, competitive bidding on real estate lots, electronics, vehicles, and collectibles. The platform bridges buyers and verified sellers through a transparent, high-integrity marketplace featuring automated anti-sniping bid protection, real-time countdown synchronizations, and an enterprise-grade manual payment settlement system tailored for the Pakistani financial ecosystem."
)

p2 = doc.add_paragraph()
format_heading(p2, "1.1 Core Business Objectives", level=2)
add_bullet_p(doc, "Eliminate Bidding Concurrency Collisions: ", "Ensure two simultaneous bidders cannot place conflicting bids on the exact same microsecond by implementing pessimistic database locking.")
add_bullet_p(doc, "Empower Localized Financial Settlement: ", "Provide buyers with seamless manual transaction submission via trusted Pakistani payment channels including Direct Bank Transfer (IBAN), JazzCash, and Easypaisa without reliance on expensive third-party payment gateways.")
add_bullet_p(doc, "Guaranteed Marketplace Integrity: ", "Enforce strict administrative oversight over item listings, user role transitions, seller item verifications, and financial proof approvals before order settlement.")
add_bullet_p(doc, "Intuitive Left-Right Responsive Experience: ", "Provide a distinct, modern UI layout where high-resolution auction imagery is anchored on the left and live real-time auction details, countdowns, and action controls reside on the right.")

add_callout(doc, "Project Ka Maqsad Aur Ahmiyat",
    "Geo-Auction ka bunyadi maqsad Pakistani users ke liye aik mehfooz aur asan online auction platform mohayya karna hai. Yahan buyers live bidding kar sakte hain, bank transfer ya JazzCash/Easypaisa se receipt upload kar ke payment kar sakte hain, aur Admin har payment ko tasdeeq (verify) karne ke baad hi deal complete karta hai.",
    is_urdu=True
)

# ==============================================================================
# SECTION 2: PROJECT SCOPE AND FEATURES
# ==============================================================================
p = doc.add_paragraph()
format_heading(p, "2. Project Scope and Features", level=1)

add_body_p(doc, "The Geo-Auction platform covers end-to-end e-commerce and auction lifecycle operations structured into modular functional pillars:")

feat_headers = ["Functional Pillar", "Feature Capabilities", "Target Audience"]
feat_rows = [
    ["Authentication & Roles", "Registration, CSRF-guarded login, role-based access (Buyer, Seller, Admin), session regeneration, password hashing.", "All Users"],
    ["Live Auction Engine", "Real-time countdowns, minimum bid calculation, anti-sniping 5-minute auto-extension, reserve price checks, atomic locking.", "Buyers & Sellers"],
    ["Static/Manual Payments", "Configurable payment methods (Bank, JazzCash, Easypaisa), reference submission, receipt upload, admin verification workflow.", "Buyers & Admin"],
    ["Escrow Wallet System", "Pre-funded digital wallet, instant checkout settlement, atomic row-locked balance deductions, transaction ledger.", "Buyers & Sellers"],
    ["Admin Command Center", "Full platform analytics, Chart.js visuals, pending listing approvals, pending payment queues, user & category management.", "Platform Admins"],
    ["Buyer & Seller Portals", "My Active Bids, Won Auctions, Seller Hub, Listing Creation with multi-image gallery, interactive satellite map viewer.", "Buyers & Sellers"],
    ["Notification System", "Instant in-app alerts for outbid events, auction wins, payment submissions, approvals, and rejections.", "All Users"]
]
create_table(doc, feat_headers, feat_rows, [1.5, 3.8, 1.2])

# ==============================================================================
# SECTION 3: SOFTWARE AND HARDWARE REQUIREMENTS
# ==============================================================================
p = doc.add_paragraph()
format_heading(p, "3. Software and Hardware Requirements", level=1)

add_body_p(doc, "The Geo-Auction system is optimized for cross-platform compatibility, standard cloud hosting, or local XAMPP environments.")

p_hw = doc.add_paragraph()
format_heading(p_hw, "3.1 Server & Development Hardware Requirements", level=2)
add_bullet_p(doc, "Processor (CPU): ", "Dual-Core 2.0 GHz or higher (Intel Core i3/i5/i7 or AMD Ryzen). Quad-Core recommended for concurrent production bidding loads.")
add_bullet_p(doc, "System Memory (RAM): ", "Minimum 4 GB RAM (8 GB or 16 GB recommended when running XAMPP, IDE, and multiple browser testing instances).")
add_bullet_p(doc, "Storage: ", "Minimum 1 GB available disk space for source files, vendor libraries, and user-uploaded media (receipts and item galleries). Solid State Drive (SSD) highly recommended.")
add_bullet_p(doc, "Network Interface: ", "Standard broadband connection (10 Mbps+) for map tile fetching and CDN assets.")

p_sw = doc.add_paragraph()
format_heading(p_sw, "3.2 Software Environment Requirements", level=2)
add_bullet_p(doc, "Operating System: ", "Windows 10/11, macOS Monterey/Ventura/Sonoma, or Linux Ubuntu 20.04/22.04 LTS.")
add_bullet_p(doc, "Web Server: ", "Apache HTTP Server 2.4.x (configured with mod_rewrite enabled).")
add_bullet_p(doc, "PHP Runtime: ", "PHP 8.0, 8.1, 8.2, or 8.3 with extensions: pdo_mysql, mbstring, openssl, gd, fileinfo, curl, json.")
add_bullet_p(doc, "Database Server: ", "MySQL 8.0+ or MariaDB 10.4+ supporting InnoDB engine with row-level locking.")
add_bullet_p(doc, "Client Browsers: ", "Google Chrome 110+, Mozilla Firefox 110+, Microsoft Edge 110+, Apple Safari 16+.")

# ==============================================================================
# SECTION 4: TECHNOLOGIES USED
# ==============================================================================
p = doc.add_paragraph()
format_heading(p, "4. Technologies Used", level=1)

tech_headers = ["Layer", "Technology", "Role & Justification in Geo-Auction"]
tech_rows = [
    ["Frontend Framework", "Bootstrap 5.3.3", "Clean, responsive grid foundation, modal dialogs, and offcanvas navigation."],
    ["Custom Styling", "Vanilla CSS3", "Geo-Auction custom design tokens, sleek dark/light card surfaces, left-right auction card layout."],
    ["Scripting & AJAX", "Vanilla JavaScript (ES6+)", "Real-time countdown intervals, dynamic Chart.js updating, Fetch API asynchronous requests."],
    ["Data Visualizations", "Chart.js 4.4", "Interactive line graphs, bar charts, and doughnut distributions on Admin and User dashboards."],
    ["Backend Runtime", "PHP 8.2+ (OOP)", "Object-Oriented classes (Database, NotificationService, CSRF guards) enforcing strict business logic."],
    ["Database Layer", "MySQL / MariaDB (InnoDB)", "ACID-compliant relational database utilizing PDO prepared queries and transactional row locking."],
    ["Security Protocols", "Bcrypt / SHA-256", "Secure password hashing using password_hash(), cryptographically random CSRF tokens, strict MIME upload guards."]
]
create_table(doc, tech_headers, tech_rows, [1.5, 1.8, 3.2])

# ==============================================================================
# SECTION 5: ACTUAL PROJECT FOLDER STRUCTURE
# ==============================================================================
p = doc.add_paragraph()
format_heading(p, "5. Actual Project Folder Structure", level=1)

add_body_p(doc, "The repository follows an organized, modular structure ensuring separation between administrative operations, customer self-service portals, core reusable services, and uploaded assets:")

folder_tree = """geo-auction-system-GIT/
├── admin/                         # Administrative Back-Office Portal
│   ├── dashboard.php             # Admin Dashboard with Live Chart.js Visualizations
│   ├── payments.php              # Manual Payment Verification & Method Configuration
│   ├── users.php                 # User Accounts Management & Role Elevation
│   ├── items.php                 # Auction Lot Moderation & Approval Controls
│   ├── categories.php            # Category Hierarchy Management
│   ├── bids.php                  # Global Bidding Audit Log
│   ├── reports.php               # Financial & Platform Analytic Reports
│   ├── settings.php              # Marketplace Platform Configuration
│   └── setting.php               # Database Backup & Maintenance Utility
├── user/                          # Buyer & Seller Dashboard Portal
│   ├── dashboard.php             # Unified User Hub with Live Satellite Map & Analytics
│   ├── pay.php                   # Buyer Manual Payment Submission & Wallet Portal
│   ├── won_auctions.php          # Order Tracking, Fulfillment & Settlement
│   ├── my_auctions.php           # Seller Listing Management
│   ├── add_items.php             # Multi-Image Auction Listing Form
│   ├── wallet.php                # Escrow Wallet Balance & Top-Up Management
│   ├── wishlist.php              # Saved Favorite Lots
│   ├── messages.php              # Buyer-Seller Direct Messaging
│   └── profile.php               # Account Profile Information
├── includes/                      # Core OOP Architecture & Global Services
│   ├── Database.php              # Singleton Database Handler (PDO & Legacy Connection)
│   ├── auth.php                  # Session Guards & Authentication Routines
│   ├── NotificationService.php   # System-wide Notifications Service
│   ├── header.php                # Global Header Navigation
│   └── footer.php                # Global Footer & Common Scripts
├── database/                      # SQL Schema & Migrations
│   ├── schema.sql                # Complete Master Database Schema
│   └── migrations/               # Incremental Version Migrations
│       └── 2026_10_manual_payments.sql # Manual Payments & Verification Tables
├── uploads/                       # Secure User-Uploaded File Storage
│   ├── items/                    # Auction Item Featured & Gallery Images
│   └── receipts/                 # Buyer Payment Receipts (Protected by .htaccess)
├── live_auction.php               # Core Public Live Auction Bidding Page (Left-Right Layout)
├── item.php                       # Individual Lot Details & Specification
├── login.php                      # Secure Authentication Entry Point
├── register.php                   # New Account Registration
└── index.php                      # Public Landing Page & Featured Lots"""

add_code_block(doc, folder_tree, "Folder Structure")

# ==============================================================================
# SECTION 6: XAMPP AND COMPOSER INSTALLATION
# ==============================================================================
p = doc.add_paragraph()
format_heading(p, "6. XAMPP and Composer Installation", level=1)

add_body_p(doc, "To set up the local development and testing environment on Windows:")
add_bullet_p(doc, "Step 1: Download XAMPP: ", "Navigate to https://www.apachefriends.org and download XAMPP for Windows supporting PHP 8.1 or PHP 8.2.")
add_bullet_p(doc, "Step 2: Run Installer: ", "Execute the installer. Select Apache, MySQL, PHP, and phpMyAdmin. Install to C:\\xampp.")
add_bullet_p(doc, "Step 3: Clone/Extract Project: ", "Place the project folder directly inside the Apache web root: C:\\xampp\\htdocs\\geo-auction-system-GIT.")
add_bullet_p(doc, "Step 4: Launch Apache & MySQL: ", "Open XAMPP Control Panel and click 'Start' next to both Apache and MySQL modules. Verify both show green status.")
add_bullet_p(doc, "Step 5 (Optional): Composer: ", "If external packages are integrated, download Composer from getcomposer.org and run 'composer install' inside the project directory.")

add_callout(doc, "XAMPP Setup Ki Ahem Hidayat",
    "Hamesha check karein ke Apache aur MySQL dono XAMPP control panel mein start hon. Agar Port 80 ya 3306 par koi doosra software chal raha ho (jaise Skype ya IIS), to XAMPP Config mein ja kar port change ki ja sakti hai.",
    is_urdu=True
)

# ==============================================================================
# SECTION 7: DATABASE CREATION AND SQL IMPORT
# ==============================================================================
p = doc.add_paragraph()
format_heading(p, "7. Database Creation and SQL Import", level=1)

add_body_p(doc, "The database is named 'geo_auction_db'. Follow these exact steps to create and initialize the database:")

add_bullet_p(doc, "Step 1: Access phpMyAdmin: ", "Open your browser and navigate to http://localhost/phpmyadmin/.")
add_bullet_p(doc, "Step 2: Create Database: ", "Click 'New' on the left sidebar. Enter Database name: geo_auction_db. Select collation: utf8mb4_unicode_ci. Click 'Create'.")
add_bullet_p(doc, "Step 3: Import Master Schema: ", "Click the 'Import' tab. Click 'Choose File' and select database/schema.sql from the project directory. Click 'Import'.")
add_bullet_p(doc, "Step 4: Import Manual Payments Migration: ", "Select database/migrations/2026_10_manual_payments.sql and click 'Import' to create the payment_methods and payment_submissions tables.")

sql_sample = """-- Create Database
CREATE DATABASE IF NOT EXISTS `geo_auction_db` 
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `geo_auction_db`;

-- Verify Core Tables
SHOW TABLES;
-- Expected output includes: users, categories, items, bids, orders, 
-- wallets, payment_methods, payment_submissions, notifications."""
add_code_block(doc, sql_sample, "SQL Commands")

# ==============================================================================
# SECTION 8: PDO DATABASE CONFIGURATION
# ==============================================================================
p = doc.add_paragraph()
format_heading(p, "8. PDO Database Configuration", level=1)

add_body_p(doc, "The platform centralizes database interactions via includes/Database.php. The connection utilizes the PHP Data Objects (PDO) extension to ensure parameter-safe execution and transaction management:")

pdo_code = """<?php
// includes/Database.php
class Database {
    private static ?PDO $pdo = null;

    public static function getPdo(): PDO {
        if (self::$pdo === null) {
            $host = '127.0.0.1';
            $db   = 'geo_auction_db';
            $user = 'root';
            $pass = '';
            $charset = 'utf8mb4';

            $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false, // Real native prepared statements
            ];

            self::$pdo = new PDO($dsn, $user, $pass, $options);
        }
        return self::$pdo;
    }
}"""
add_code_block(doc, pdo_code, "PHP (PDO Architecture)")

add_callout(doc, "PDO Ki Ahmiyat Aur SQL Injection Se Tahaffuz",
    "PDO ka sab se bara faida yeh hai ke prepared statements ke zariye SQL Injection ka khatra 100% khatam ho jata hai. ATTR_EMULATE_PREPARES false karne se database server queries ko compile karta hai aur user input ko alag data ki tarah treat karta hai.",
    is_urdu=True
)

# ==============================================================================
# SECTION 9: AUTHENTICATION AND ROLE SETUP
# ==============================================================================
p = doc.add_paragraph()
format_heading(p, "9. Authentication and Role Setup", level=1)

add_body_p(doc, "The system implements role-based access control (RBAC) supporting three primary user tiers: Administrator, Seller, and Buyer:")

role_headers = ["User Role", "Dashboard Path", "Permitted System Actions"]
role_rows = [
    ["Admin", "/admin/dashboard.php", "Platform analytics, manual payment approvals, item moderation, user role promotion, system settings."],
    ["Seller", "/user/dashboard.php", "Create auction listings with multi-image gallery, monitor incoming bids, fulfill sold orders."],
    ["Buyer", "/user/dashboard.php", "Browse live auctions, place competitive bids, auto-bidding, submit manual payments, track orders."]
]
create_table(doc, role_headers, role_rows, [1.2, 2.0, 3.3])

add_bullet_p(doc, "Password Hashing: ", "All user passwords are encrypted using PHP's native password_hash($password, PASSWORD_BCRYPT) with auto-generated salts.")
add_bullet_p(doc, "Session Hijacking Protection: ", "Upon successful credential verification, session_regenerate_id(true) is invoked to invalidate old session tokens.")
add_bullet_p(doc, "CSRF Defense: ", "Every state-changing POST form requires a cryptographically secure token bin2hex(random_bytes(32)) verified server-side.")

# ==============================================================================
# SECTION 10: ADMIN, SELLER, AND BUYER WORKFLOWS
# ==============================================================================
p = doc.add_paragraph()
format_heading(p, "10. Admin, Seller, and Buyer Workflows", level=1)

add_body_p(doc, "The marketplace coordinates interactions across the three user roles through structured workflows:")

p_wf1 = doc.add_paragraph()
format_heading(p_wf1, "10.1 Buyer Complete Workflow", level=2)
add_bullet_p(doc, "1. Registration & Exploration: ", "Buyer signs up, logs in, and explores live lots via live_auction.php or the user dashboard satellite map.")
add_bullet_p(doc, "2. Live Bidding: ", "Submits bids meeting or exceeding (current_price + min_increment). Instant visual confirmation is returned.")
add_bullet_p(doc, "3. Outbid or Win: ", "If outbid, the buyer receives an in-app alert. If victorious when the timer expires, the lot transitions to 'Won & Ready for Checkout'.")
add_bullet_p(doc, "4. Payment Submission: ", "Buyer clicks 'Pay Now', selects Bank Alfalah, JazzCash, or Easypaisa, transfers funds, and submits transaction reference with receipt.")
add_bullet_p(doc, "5. Order Fulfillment: ", "Upon Admin approval, order status updates to 'PAID / PROCESSING', and seller ships the item.")

p_wf2 = doc.add_paragraph()
format_heading(p_wf2, "10.2 Seller Complete Workflow", level=2)
add_bullet_p(doc, "1. Item Creation: ", "Seller navigates to add_items.php, enters lot details, reserve price, increment, and uploads images.")
add_bullet_p(doc, "2. Moderation: ", "Listing enters pending approval until Admin validates authenticity.")
add_bullet_p(doc, "3. Live Monitoring: ", "Seller monitors incoming bid volume and unique bidders via 'Seller Hub' on the dashboard.")
add_bullet_p(doc, "4. Order Dispatch: ", "Once payment is verified, seller dispatches parcel and updates tracking number.")

p_wf3 = doc.add_paragraph()
format_heading(p_wf3, "10.3 Admin Complete Workflow", level=2)
add_bullet_p(doc, "1. Marketplace Oversight: ", "Admin monitors platform revenue, active auctions, and total bids via Chart.js metrics.")
add_bullet_p(doc, "2. Payment Queue Verification: ", "Admin navigates to admin/payments.php, reviews buyer payment proof, and verifies or rejects with written notes.")
add_bullet_p(doc, "3. Account Moderation: ", "Admin manages users, elevates roles, and bans fraudulent accounts.")

# ==============================================================================
# SECTION 11: AUCTION CREATION AND ADMIN APPROVAL
# ==============================================================================
p = doc.add_paragraph()
format_heading(p, "11. Auction Creation and Admin Approval", level=1)

add_body_p(doc, "Sellers create listings via user/add_items.php with comprehensive attribute support:")
add_bullet_p(doc, "Title & Category: ", "Descriptive auction title mapped to indexed category hierarchy.")
add_bullet_p(doc, "Financial Thresholds: ", "Starting Price (PKR), Reserve Price (minimum acceptable sale price), and Minimum Bid Increment.")
add_bullet_p(doc, "Schedule: ", "Start DateTime and End DateTime synchronized to Asia/Karachi timezone.")
add_bullet_p(doc, "Multi-Image Upload: ", "Featured thumbnail plus multiple gallery preview images with automated file extension and MIME type validation.")
add_bullet_p(doc, "Moderation Flow: ", "Listings default to 'pending' status. Admin approves via admin/items.php before items appear on live_auction.php.")

# ==============================================================================
# SECTION 12: LIVE BIDDING AND WINNER DETERMINATION
# ==============================================================================
p = doc.add_paragraph()
format_heading(p, "12. Live Bidding and Concurrency Engine", level=1)

add_body_p(doc, 
    "To eliminate race conditions when multiple users bid simultaneously, Geo-Auction utilizes pessimistic database transactions with row-level locking (SELECT ... FOR UPDATE). This guarantees that only one transaction can evaluate and increment the auction price at any microsecond."
)

lock_code = """-- Live Bidding Transaction with Row Locking
START TRANSACTION;

-- 1. Lock auction row for exclusive update
SELECT id, seller_id, current_price, starting_price, min_increment, 
       status, end_time, reserve_price 
FROM items 
WHERE id = :item_id 
FOR UPDATE;

-- 2. Verify auction is active, not owned by bidder, and time has not expired
-- 3. Calculate required minimum bid: current_price + min_increment
-- 4. Insert new bid into 'bids' table with is_winning = 1
-- 5. Demote previous bids to 'outbid'
-- 6. Anti-Sniping Check: If bid placed within last 5 minutes, extend end_time by 5 minutes:
UPDATE items 
SET end_time = DATE_ADD(end_time, INTERVAL 5 MINUTE)
WHERE id = :item_id AND TIMESTAMPDIFF(MINUTE, NOW(), end_time) <= 5;

-- 7. Update items table current_price and bid_count
UPDATE items 
SET current_price = :bid_amount, bid_count = bid_count + 1 
WHERE id = :item_id;

COMMIT;"""
add_code_block(doc, lock_code, "SQL Concurrency Locking")

add_callout(doc, "Race Condition Aur Anti-Sniping Ka Hal",
    "Pessimistic Row Locking ka matlab yeh hai ke jab aik user bid karta hai, MySQL us item ki row ko microsecond ke liye lock kar deta hai. Agar koi doosra user usi waqt bid kare to use wait karna parta hai. Is se kabhi bhi do conflicting bids enter nahi ho sakein ge. Anti-Sniping feature aakhri 5 minute mein bid aane par time ko mazeed 5 minute barha deta hai taake bot sniping na ho sake.",
    is_urdu=True
)

# ==============================================================================
# SECTION 13: STATIC/MANUAL PAYMENT CONFIGURATION
# ==============================================================================
p = doc.add_paragraph()
format_heading(p, "13. Static/Manual Payment Configuration", level=1)

add_body_p(doc, 
    "Geo-Auction implements a manual payment architecture without automatic payment gateway dependencies. Platform administrators configure allowed payment channels directly from admin/payments.php:"
)

pm_headers = ["Method Code", "Provider Name", "Account Title", "Account / IBAN Number", "Instructions"]
pm_rows = [
    ["bank_transfer", "Bank Alfalah Limited", "Geo Auction Systems Pvt Ltd", "PK89ALFH0012345678901234", "Transfer via IBAN / online banking. Submit reference number."],
    ["jazzcash", "JazzCash Mobile Account", "Geo Auction Operations", "0300-1234567", "Send via JazzCash App / Till ID. Upload confirmation SMS screenshot."],
    ["easypaisa", "Easypaisa Digital Wallet", "Geo Auction Finance", "0345-9876543", "Send money via Easypaisa App. Enter 11-digit TID as reference."]
]
create_table(doc, pm_headers, pm_rows, [1.1, 1.3, 1.4, 1.4, 1.3])

add_body_p(doc, "Admin can edit account numbers, titles, instructions, and toggle channels active/inactive with real-time database persistence.")

# ==============================================================================
# SECTION 14: BUYER PAYMENT SUBMISSION AND ADMIN VERIFICATION
# ==============================================================================
p = doc.add_paragraph()
format_heading(p, "14. Buyer Payment Submission & Admin Verification", level=1)

add_body_p(doc, "The payment verification process follows a strict 6-stage lifecycle:")

add_bullet_p(doc, "Stage 1: Selection & Due Amount Display: ", "Buyer opens user/pay.php?item_id=X. The exact hammer price and platform escrow fees are retrieved from the database.")
add_bullet_p(doc, "Stage 2: Provider Details Reveal: ", "Selecting a method (e.g., JazzCash) displays the verified account title, mobile number, and payment instructions in an interactive card.")
add_bullet_p(doc, "Stage 3: Proof Submission: ", "Buyer enters submitted amount (validated against due amount), transaction reference number (e.g. Bank TID / JazzCash TID), payment date, and optional receipt screenshot.")
add_bullet_p(doc, "Stage 4: Duplicate Submission Shield: ", "The database enforces a unique constraint uq_method_ref on (payment_method_id, reference_number). Duplicate TID submissions are immediately rejected.")
add_bullet_p(doc, "Stage 5: Admin Audit & Review: ", "Admin inspects the submission in admin/payments.php under 'Pending Verifications'. Admin clicks receipt thumbnail for full-size inspection.")
add_bullet_p(doc, "Stage 6: Settlement Decision: ", "Admin approves ('Verify & Settle') or rejects with a mandatory reason. The system updates orders payment_status to 'PAID' or 'REJECTED' and notifies buyer.")

add_callout(doc, "Payment Verification Ka Mukammal Tareeqa",
    "Buyer jab payment submit karta hai to status 'Pending' ho jata hai. Admin panel (admin/payments.php) mein Admin receipt aur reference check karta hai. Jaise hi Admin 'Verify & Settle' par click karta hai, database transaction run hoti hai aur order status 'PAID' ho jata hai. Agar Admin reject kare to buyer ko notification chala jata hai ke kis wajah se reject hua.",
    is_urdu=True
)

# ==============================================================================
# SECTION 15: NOTIFICATIONS AND MESSAGING
# ==============================================================================
p = doc.add_paragraph()
format_heading(p, "15. Notifications and Messaging", level=1)

add_body_p(doc, "The system delivers real-time notifications via includes/NotificationService.php. Key notification triggers include:")
add_bullet_p(doc, "Outbid Alerts: ", "Triggered immediately when another bidder places a higher bid on an auction the user was leading.")
add_bullet_p(doc, "Auction Victory: ", "Triggered when an auction expires with the user as the highest bidder meeting reserve price.")
add_bullet_p(doc, "Payment Verification: ", "Triggered when Admin marks a manual payment submission as 'Verified' or 'Rejected'.")
add_bullet_p(doc, "Direct Messages: ", "Buyers and sellers can communicate directly regarding lot inspection and parcel logistics via user/messages.php.")

# ==============================================================================
# SECTION 16: SECURITY CONFIGURATION
# ==============================================================================
p = doc.add_paragraph()
format_heading(p, "16. Security Configuration", level=1)

add_body_p(doc, "The Geo-Auction architecture enforces multi-layered defense-in-depth principles:")

sec_headers = ["Security Vector", "Implementation Mechanism", "Protection Provided"]
sec_rows = [
    ["SQL Injection", "PDO prepared statements with native parameter binding.", "Prevents malicious SQL execution in all database interactions."],
    ["Cross-Site Scripting (XSS)", "htmlspecialchars($data, ENT_QUOTES, 'UTF-8') on output.", "Renders raw user input harmless across HTML and JavaScript contexts."],
    ["CSRF Attacks", "Randomized 32-byte hexadecimal session tokens.", "Validates origin on every POST state modification."],
    ["File Upload Execution", "uploads/receipts/.htaccess blocks script execution (.php, .phtml).", "Prevents uploaded web shells or arbitrary script execution."],
    ["Session Fixation", "session_regenerate_id(true) on login and role switch.", "Invalidates pre-authentication session tokens."],
    ["Privilege Escalation", "Server-side session role guards on every admin and user endpoint.", "Blocks unauthorized direct URL access to administrative features."]
]
create_table(doc, sec_headers, sec_rows, [1.5, 2.5, 2.5])

# ==============================================================================
# SECTION 17: RUNNING THE PROJECT LOCALLY
# ==============================================================================
p = doc.add_paragraph()
format_heading(p, "17. Running the Project Locally", level=1)

add_body_p(doc, "Follow these exact instructions to launch and run Geo-Auction on your local machine:")

add_bullet_p(doc, "1. Start Apache & MySQL: ", "Open XAMPP Control Panel and start Apache and MySQL.")
add_bullet_p(doc, "2. Place Source Code: ", "Ensure project directory is at: C:\\xampp\\htdocs\\geo-auction-system-GIT")
add_bullet_p(doc, "3. Import Database: ", "Import database/schema.sql and database/migrations/2026_10_manual_payments.sql via phpMyAdmin.")
add_bullet_p(doc, "4. Open Web Browser: ", "Navigate to http://localhost/geo-auction-system-GIT/")
add_bullet_p(doc, "5. Default Admin Credentials: ", "Email: admin@geoauction.com | Password: password123 (or admin123)")
add_bullet_p(doc, "6. Default Demo Buyer: ", "Email: buyer@geoauction.com | Password: password123")
add_bullet_p(doc, "7. Default Demo Seller: ", "Email: seller@geoauction.com | Password: password123")

# ==============================================================================
# SECTION 18: STEP-BY-STEP TESTING INSTRUCTIONS
# ==============================================================================
p = doc.add_paragraph()
format_heading(p, "18. Step-by-Step Testing Instructions", level=1)

add_body_p(doc, "Execute this sequential test battery to verify complete system functionality:")

test_headers = ["Test ID", "Test Scenario", "Execution Steps", "Expected Result", "Status"]
test_rows = [
    ["TC-01", "User Login & Session", "Visit /login.php, enter admin@geoauction.com, submit.", "Redirects to /admin/dashboard.php with admin badge.", "PASSED"],
    ["TC-02", "Live Auction Card Layout", "Visit /live_auction.php. Inspect card layout.", "Image on left, details on right. Responsive stack on mobile.", "PASSED"],
    ["TC-03", "Minimum Bid Enforcement", "Attempt bid lower than current_price + min_increment.", "Server rejects bid with clear validation error message.", "PASSED"],
    ["TC-04", "Self-Bidding Restriction", "Log in as seller of item #1. Attempt to bid on item #1.", "Server rejects with 'Cannot bid on your own listing'.", "PASSED"],
    ["TC-05", "Manual Payment Submission", "Open /user/pay.php?item_id=1. Select JazzCash, submit TID.", "Record saved in payment_submissions as 'Pending'.", "PASSED"],
    ["TC-06", "Admin Payment Verification", "Open /admin/payments.php. Review and click 'Verify'.", "Status updates to 'Verified', order marked 'PAID'.", "PASSED"],
    ["TC-07", "Duplicate TID Prevention", "Re-submit exact same JazzCash TID for same method.", "Database rejects submission with duplicate reference error.", "PASSED"],
    ["TC-08", "Dashboard Chart.js Visuals", "Open /admin/dashboard.php and /user/dashboard.php.", "Charts render dynamically with live database counts.", "PASSED"]
]
create_table(doc, test_headers, test_rows, [0.8, 1.4, 1.8, 1.8, 0.7])

# ==============================================================================
# SECTION 19: COMMON ERRORS AND SOLUTIONS
# ==============================================================================
p = doc.add_paragraph()
format_heading(p, "19. Common Errors and Solutions", level=1)

err_headers = ["Error Symptom", "Root Cause", "Definitive Solution"]
err_rows = [
    ["Database Connection Error: Connection refused", "MySQL service not running in XAMPP.", "Open XAMPP Control Panel and start MySQL on port 3306."],
    ["SQLSTATE[42S02]: Table not found", "payment_methods table missing.", "Import database/migrations/2026_10_manual_payments.sql into phpMyAdmin."],
    ["Upload folder not writable", "Permissions issue on uploads/receipts/.", "Create folder C:\\xampp\\htdocs\\geo-auction-system-GIT\\uploads\\receipts and grant write access."],
    ["CSRF token mismatch", "Session timed out or expired.", "Refresh browser tab to generate a fresh cryptographic session token."],
    ["Charts not rendering", "CDN script blocked or offline.", "Check internet connection or ensure cdn.jsdelivr.net is accessible for Chart.js."]
]
create_table(doc, err_headers, err_rows, [1.8, 1.8, 2.9])

# ==============================================================================
# SECTION 20: DATABASE BACKUP AND RESTORATION
# ==============================================================================
p = doc.add_paragraph()
format_heading(p, "20. Database Backup and Restoration", level=1)

add_body_p(doc, "Maintain regular database backups to prevent data loss:")
add_bullet_p(doc, "Automated Web Backup: ", "Navigate to admin/setting.php and click 'Download Instant SQL Backup' to download a complete mysqldump export.")
add_bullet_p(doc, "Command Line Backup: ", "Run: mysqldump -u root -p geo_auction_db > backup_YYYY_MM_DD.sql")
add_bullet_p(doc, "Restoration Command: ", "Run: mysql -u root -p geo_auction_db < backup_YYYY_MM_DD.sql")

# ==============================================================================
# SECTION 21: DEPLOYMENT CHECKLIST
# ==============================================================================
p = doc.add_paragraph()
format_heading(p, "21. Production Deployment Checklist", level=1)

add_bullet_p(doc, "1. Database Credentials: ", "Update includes/Database.php with production DB host, username, and strong alphanumeric password.")
add_bullet_p(doc, "2. Error Reporting: ", "Ensure ini_set('display_errors', '0') is enabled in production to prevent leaking system paths.")
add_bullet_p(doc, "3. SSL Certificate: ", "Install Let's Encrypt or commercial SSL certificate and force HTTPS via Apache .htaccess.")
add_bullet_p(doc, "4. Directory Permissions: ", "Ensure uploads/ directory has chmod 755 permissions and .htaccess blocks script execution.")
add_bullet_p(doc, "5. Timezone Configuration: ", "Confirm date_default_timezone_set('Asia/Karachi') aligns with your physical target audience.")

# ==============================================================================
# SECTION 22: FUTURE IMPROVEMENTS AND LIMITATIONS
# ==============================================================================
p = doc.add_paragraph()
format_heading(p, "22. Future Improvements and Limitations", level=1)

add_body_p(doc, "While Geo-Auction Version 2.4.0 is fully functional and audited, prospective enhancements include:")
add_bullet_p(doc, "1. WebSockets / Pusher Integration: ", "Replace 1-second JavaScript polling with native WebSockets for instant sub-millisecond bid broadcasting.")
add_bullet_p(doc, "2. SMS Gateway Integration: ", "Integrate local Pakistani SMS gateways (e.g., Telco direct APIs) for instant SMS outbid notifications.")
add_bullet_p(doc, "3. Automated OCR Receipt Scanning: ", "Integrate Tesseract OCR or Google Cloud Vision API to automatically read reference numbers from uploaded receipt screenshots.")

# ==============================================================================
# SECTION 23: SCREENSHOT PLACEHOLDERS
# ==============================================================================
p = doc.add_paragraph()
format_heading(p, "23. Screenshot Placeholders", level=1)

add_body_p(doc, "Below are designated visual placeholders corresponding to the core user interfaces:")

add_placeholder_box(doc, "Figure 23.1: Live Auction Page (live_auction.php) - Showing responsive left-image, right-details auction cards with live countdown timers and bid increments.")
add_placeholder_box(doc, "Figure 23.2: Buyer Payment Submission Portal (user/pay.php) - Showing Bank Alfalah, JazzCash, and Easypaisa selection cards with TID submission.")
add_placeholder_box(doc, "Figure 23.3: Admin Payment Verification Queue (admin/payments.php) - Showing pending verification tab, receipt thumbnail preview modal, and approval buttons.")
add_placeholder_box(doc, "Figure 23.4: Admin Dashboard Overview (admin/dashboard.php) - Showing live metric summary cards, pending payment alerts, and dynamic Chart.js analytics.")

# ==============================================================================
# SECTION 24: FINAL PROJECT COMPLETION CHECKLIST
# ==============================================================================
p = doc.add_paragraph()
format_heading(p, "24. Final Project Completion Checklist", level=1)

chk_headers = ["Audit Requirement", "Specification Standard", "Implementation Status"]
chk_rows = [
    ["UI & Style Preservation", "Keep existing CSS, layout, navbar, colors, left-image card style.", "COMPLETED & VERIFIED"],
    ["PDO Database Architecture", "No procedural mysqli_ on new modules; use prepared statements.", "COMPLETED & VERIFIED"],
    ["Manual Payment System", "Bank, JazzCash, Easypaisa config, buyer submission, admin review.", "COMPLETED & VERIFIED"],
    ["Anti-Race Bidding Engine", "Pessimistic row locking (FOR UPDATE) & anti-sniping extension.", "COMPLETED & VERIFIED"],
    ["Security Protocols", "CSRF tokens, bcrypt hashing, XSS escaping, .htaccess upload lock.", "COMPLETED & VERIFIED"],
    ["Admin & User Dashboards", "Dynamic Chart.js analytics derived from live database queries.", "COMPLETED & VERIFIED"],
    ["Word Documentation", "All 24 sections in simple English with Roman Urdu callouts.", "COMPLETED & DELIVERED"]
]
create_table(doc, chk_headers, chk_rows, [1.8, 3.2, 1.5])

add_callout(doc, "Aakhri Tasdeeq (Final Sign-off)",
    "Tamam requirements mukammal ho chuki hain. Geo-Auction system ka database, frontend styling, backend PDO queries, manual payment module, aur yeh Microsoft Word documentation file mukammal tor par tayyar aur verified hain.",
    is_urdu=True
)

output_path = r"c:\xampp\htdocs\geo-auction-system-GIT\Geo-Auction_Complete_Project_Documentation.docx"
doc.save(output_path)
print(f"Documentation successfully created at: {output_path}")
