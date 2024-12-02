# hozio-dynamic-tags

== Changelog ==

= 3.14.53 (2024-12-02) =
* Added functionality to sync pages with parent "Services" to WordPress navigation menus.
* Implemented automatic removal of menu items when a page associated with "Services" is deleted, preventing "Invalid" menu items from appearing.
* Enhanced the sync mechanism to update menus when a page's parent changes, ensuring menu consistency.
* Ensured that all existing child pages of the "Services" parent are included in the relevant menus upon plugin activation or update.
* Updated the process for handling pages under the "Services" parent, ensuring they are correctly placed within the main menu, toggle menu, and services menu based on their position and parent-child relationship.

This update improves the overall user experience by automatically managing menu items associated with "Services" pages and removing invalid menu items when pages are deleted.



QUERY FILTER ADDITION:
Description:
This feature introduces a custom query modification for Elementor's Loop Grid and Posts widgets to filter and display only the child pages of the "Services" page. By using a custom query ID (services_children), the query is modified to return only pages that are direct children of the Services page, based on its parent-child relationship.

Functionality:
When the Query ID is set to services_children in Elementor's Loop Grid or Posts widget, the query is automatically filtered to:

Include only Pages (post_type is set to page).
Show only the child pages of the page with the title "Services" (post_parent is set to the Services page ID).
Purpose:
This feature allows site owners to display dynamic content related to services (or similar hierarchical content) by automatically fetching and displaying child pages of the "Services" page. It helps organize related content under a main parent page, such as service offerings, and allows for clean, structured navigation.

Benefits:

Easily filter and display child pages related to a parent page.
Integrates with Elementor’s Loop Grid and Posts widgets using a custom query ID.
Ideal for showcasing hierarchical content like sub-services, categories, or related pages.
No manual selection required in the query dropdown — simply use the Query ID services_children.
Example Use:
When setting up a Loop Grid or Posts widget in Elementor, use the query ID services_children under the Query ID field. This will automatically display child pages of the "Services" page, making it perfect for service-oriented sites or businesses that want to display detailed service offerings or categories.
