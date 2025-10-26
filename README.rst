=================================
Question2Answer Feature Questions & Reading Analytics Plugin
=================================

-----------
Description
-----------
This is a plugin for **Question2Answer** that provides **two major functionalities**:

1. **Featured Questions**  
   - Editors and above (as configured in admin panel) can feature/unfeature questions with a button click.  
   - Featured questions are shown as a tab in Question Listing pages.

2. **Reading Analytics Suite**  
   - Tracks what content users read (assumes a post is read when viewed).  
   - Builds **per-user statistics** and a **global leaderboard** of readers.  
   - Provides attractive charts and a daily leaderboard widget.

---

## Features

### 1. **Read Page**  
- `/read/<username>`  
- Based on explicit **“Mark as Read”** button clicks on posts.  
- Shows the list of questions a user has marked as read.  
- Includes **“Mark Unread”** button to remove items.  
- Fully integrated with category navigation and Q2A’s question list features.

### 2. **Reading Stats Page**  
- `/read-stats`  
- Based on **automatic tracking of views** (a post view = a read).  
- Provides **interactive charts** using CanvasJS:
  - Reads per day
  - Reads per category  
- Compare multiple users on the same chart.  
- Built-in filters:
  - Date range (default: last 3 months, reloads if outside range)
  - Multi-select category filter  
- Exportable data (CanvasJS supports PNG, JPG, PDF).  
- Info box with links to **Leaderboard** and **Read List**.

### 3. **Leaderboard Page**  
- `/read-leaderboard`  
- Shows **top readers** in a date range (default: yesterday).  
- Supports category filtering (multi-select).  
- **Competition-style ranking**:
  - If two users tie for rank 2 → both get rank 2, next rank is 4.  
- Configurable options:
  - Minimum reads required
  - Maximum ranks shown (default: 20)  
- Displays **avatars** and medals 🥇🥈🥉.

### 4. **Leaderboard Widget**  
- Sidebar widget showing **Yesterday’s Top Readers**.  
- Configurable **number of ranks** (via admin options).  
- Shows ties correctly.  
- Attractive small design with medals.

---

------------
Installation
------------
1. Install Question2Answer_.
2. Copy this plugin folder into your `qa-plugin/` directory.


-------
Release
-------
All code herein is Copylefted_.

.. _Copylefted: http://en.wikipedia.org/wiki/Copyleft

---------
About q2A
---------
Question2Answer is a free and open source platform for Q&A sites. For more information, visit:

http://www.question2answer.org/

