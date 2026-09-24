<div align="right">
  🇧🇷 <a href="../../README.md">Português</a> | 🇬🇧 <a href="./README.en.md">English</a> 
</div>

# GLPI New Entity Plugin - Complete structure creation in a single screen
[![GLPI 11](https://img.shields.io/badge/GLPI-11-blue.svg)](https://glpi-project.org/)
[![GLPI 12](https://img.shields.io/badge/GLPI-12-FFD700.svg)](https://glpi-project.org/)
[![License: GPL v3+](https://img.shields.io/badge/License-GPL%20v3%2B-green.svg)](https://www.gnu.org/licenses/gpl-3.0.html)

<p align="center">
  <img src="../images/logo.svg" alt="GLPI New Entity Logo" width="150"><br><br>
</p>

The **GLPI New Entity Plugin** is a fast and optimized onboarding plugin developed for GLPI 11/12. Its purpose is to automate the structural setup of a new Sector / Department into a single unified form (a "Wizard"). 

Instead of navigating through several GLPI screens to create entities, configure profiles, link users, create groups, associate technicians, and build service catalogs (ITIL categories), this plugin performs the entire process from a single screen. This guarantees extreme agility, standardized structures, and minimizes human errors.

## ✨ Main Features

*   **Fast Entity Creation:** Instantly creates a new entity under a chosen parent entity.
*   **Native Profile Cloning:** Allows you to select pre-existing profiles (like Super-Admin, Admin, Technician) and clone them, automatically naming them using the new sector's acronym prefix (e.g., `[DC] - Admin`).
*   **Direct E-mail Linking:** Just paste a list of e-mails, separated by commas or line breaks. The plugin searches for the user in the database and automatically links the profile to the new entity with recursive scope.
*   **Smart Group and Subgroup Management:**
    *   Automatically creates a "Parent Group" named with the sector's acronym `(ACRONYM)`.
    *   Allows the dynamic creation of multiple nested Subgroups.
    *   Associates support technicians to their respective subgroups (or directly to the Parent Group) from an e-mail list.
*   **Automatic ITIL Category Tree Construction:**
    *   Accepts hierarchical lists in text format using hyphens (e.g., `- Hardware`, `-- Maintenance`, `--- Repairs`).
    *   Creates perfectly nested incident and request categories with a single click.
*   **Dynamic Configuration Tabs:** Centralizes the customization of workflows and templates for the new sector, allowing you to create, edit, and apply multiple unique standards for the entity:
    *   **Ticket Templates**
    *   **Standard Follow-ups**
    *   **Standard Solutions**
    *   **Pending Reasons**
    *   **Notifications**
    *   **Standard Forms**
*   **Native Security and Access Control:** Developed following GLPI's strictest secure development standards. The interface and all creation flows (endpoints) are protected by the system's dynamic rights matrix (ACL). The installation intelligently grants access to all Super-Admin profiles natively and agnostically, without depending on hardcoded IDs and avoiding visual pollution for other users.
*   **Synchronized Editing and Updating:** In case of future adjustments, the system stores metadata and allows you to re-edit groups, e-mail links, or sector names from a central record.

## 🛠️ Requirements

*   **GLPI:** Version 11.0.0 or higher.
*   **PHP:** Versions supported by GLPI 11 or higher (8.1, 8.2, 8.3, 8.4).

## 🚀 Installation

1. Download the repository files or clone this project into your GLPI server's plugins directory:
   ```bash
   cd /var/www/html/glpi/plugins/
   git clone https://github.com/andrefelipeufcg/glpinewentity.git
   ```
2. Ensure the folder is strictly named `glpinewentity`. Fix the nomenclature if git downloads it with a different suffix.
3. Access the GLPI web interface using your **Super-Admin** user.
4. Navigate to **Setup > Plugins**.
5. In the list, locate `GLPI New Entity`, click the **Install** button (folder icon) and then the **Enable** button (power icon).

## 🖥️ How to Use

1. Log into GLPI using a user with the **Super-Admin** profile.
2. In the main top menu, navigate to **Setup > GLPI New Entity**.
![Plugin Menu](../images/screenshot1.png)
3. You will see a list (empty if it's your first time) of the sector infrastructures managed by the plugin.
![Empty Sector List](../images/screenshot2.png)
4. Click **Add** (or the "+" button depending on your theme).
![Add Button](../images/screenshot3.png)
5. Follow the Wizard instructions:
![Wizard Filling](../images/screenshot4.png)
   *   **Parent Entity and Acronym:** Indicate the location in the corporate tree and the acronym (which will name the entity and groups).
   *   **Profiles:** Determine where to clone the profile from and the e-mails of the sector managers to inherit this access recursively.
   *   **Subgroups and Technicians:** Add as many subgroups as needed, and paste all the e-mails of the responsible technicians.
   *   **Catalog (Categories):** Paste or write your category tree using "-" for sublevels.
6. Click **Save**.

The system will iterate and provision the entire environment, displaying descriptive alerts (even ignoring non-existent e-mails) and returning a base ready to operate in a matter of seconds!

![Sector List](../images/screenshot5.png)

7. **Advanced Adjustments and Configurations:** After saving, return to the list and click on the name of the newly created sector to edit it. You will notice that new configuration tabs are now available, allowing you to exclusively customize the entire operation for this entity:

![Configuration Tabs](../images/screenshot6.png)

   *   **Entity Infrastructure:** Returns to the Wizard form to edit members, groups, and categories.
   *   **Ticket Templates:** Create and associate specific ticket templates.
   *   **Standard Follow-ups:** Configure standardized texts for support replies.
   *   **Standard Solutions:** Standardize frequent solutions for ticket closure.
   *   **Pending Reasons:** Define customized reasons for pausing tickets.
   *   **Notifications:** Adjust or override sector e-mail alerts.
   *   **Standard Forms:** Manage forms (from Formcreator) linked to the sector.

## ⚙️ Technical Structure and Classes

*   `setup.php`: Initialization, hooks, and GLPI core registration.
*   `front/sector.php`: Main interface (List view).
*   `front/sector.form.php`: Main view (Wizard form and configuration tabs).
*   `src/Menu.php`: Exclusive logic for insertion and restriction (Super-Admin) in the native GLPI "Setup" top menu.
*   `src/Sector.php`: Basic CRUD class and central engine for dynamic tab rendering, extending `CommonDBTM`.
*   `src/Wizard.php`: Heart of the initial creation. Contains structural logic, database manipulation, profile copy creation, and e-mail validation.
*   `src/Builders/`: Specialized classes (`TicketTemplateBuilder`, `NotificationBuilder`, `FormBuilder`, etc.) responsible for individually applying each standard generated in the tabs.

## 📜 License

GPLv3+ - Distributed under the same open-source license guidelines of the GLPI ecosystem.
