#====================================================================================================
# START - Testing Protocol - DO NOT EDIT OR REMOVE THIS SECTION
#====================================================================================================

# THIS SECTION CONTAINS CRITICAL TESTING INSTRUCTIONS FOR BOTH AGENTS
# BOTH MAIN_AGENT AND TESTING_AGENT MUST PRESERVE THIS ENTIRE BLOCK

# Communication Protocol:
# If the `testing_agent` is available, main agent should delegate all testing tasks to it.
#
# You have access to a file called `test_result.md`. This file contains the complete testing state
# and history, and is the primary means of communication between main and the testing agent.
#
# Main and testing agents must follow this exact format to maintain testing data. 
# The testing data must be entered in yaml format Below is the data structure:
# 
## user_problem_statement: {problem_statement}
## backend:
##   - task: "Task name"
##     implemented: true
##     working: true  # or false or "NA"
##     file: "file_path.py"
##     stuck_count: 0
##     priority: "high"  # or "medium" or "low"
##     needs_retesting: false
##     status_history:
##         -working: true  # or false or "NA"
##         -agent: "main"  # or "testing" or "user"
##         -comment: "Detailed comment about status"
##
## frontend:
##   - task: "Task name"
##     implemented: true
##     working: true  # or false or "NA"
##     file: "file_path.js"
##     stuck_count: 0
##     priority: "high"  # or "medium" or "low"
##     needs_retesting: false
##     status_history:
##         -working: true  # or false or "NA"
##         -agent: "main"  # or "testing" or "user"
##         -comment: "Detailed comment about status"
##
## metadata:
##   created_by: "main_agent"
##   version: "1.0"
##   test_sequence: 0
##   run_ui: false
##
## test_plan:
##   current_focus:
##     - "Task name 1"
##     - "Task name 2"
##   stuck_tasks:
##     - "Task name with persistent issues"
##   test_all: false
##   test_priority: "high_first"  # or "sequential" or "stuck_first"
##
## agent_communication:
##     -agent: "main"  # or "testing" or "user"
##     -message: "Communication message between agents"

# Protocol Guidelines for Main agent
#
# 1. Update Test Result File Before Testing:
#    - Main agent must always update the `test_result.md` file before calling the testing agent
#    - Add implementation details to the status_history
#    - Set `needs_retesting` to true for tasks that need testing
#    - Update the `test_plan` section to guide testing priorities
#    - Add a message to `agent_communication` explaining what you've done
#
# 2. Incorporate User Feedback:
#    - When a user provides feedback that something is or isn't working, add this information to the relevant task's status_history
#    - Update the working status based on user feedback
#    - If a user reports an issue with a task that was marked as working, increment the stuck_count
#    - Whenever user reports issue in the app, if we have testing agent and task_result.md file so find the appropriate task for that and append in status_history of that task to contain the user concern and problem as well 
#
# 3. Track Stuck Tasks:
#    - Monitor which tasks have high stuck_count values or where you are fixing same issue again and again, analyze that when you read task_result.md
#    - For persistent issues, use websearch tool to find solutions
#    - Pay special attention to tasks in the stuck_tasks list
#    - When you fix an issue with a stuck task, don't reset the stuck_count until the testing agent confirms it's working
#
# 4. Provide Context to Testing Agent:
#    - When calling the testing agent, provide clear instructions about:
#      - Which tasks need testing (reference the test_plan)
#      - Any authentication details or configuration needed
#      - Specific test scenarios to focus on
#      - Any known issues or edge cases to verify
#
# 5. Call the testing agent with specific instructions referring to test_result.md
#
# IMPORTANT: Main agent must ALWAYS update test_result.md BEFORE calling the testing agent, as it relies on this file to understand what to test next.

#====================================================================================================
# END - Testing Protocol - DO NOT EDIT OR REMOVE THIS SECTION
#====================================================================================================



#====================================================================================================
# Testing Data - Main Agent and testing sub agent both should log testing data below this section
#====================================================================================================

user_problem_statement: "Pipeline Manager India Sales integration system with PHP 5.3/8.2 and MySQL. System includes 6-level validation engine, sales data processing, opportunity management, DSM action queue, audit trail, rollback capabilities, and business rule enforcement."

backend:
  - task: "Level 1 GSTIN Validation"
    implemented: true
    working: true
    file: "classes/EnhancedValidationEngine.php"
    stuck_count: 0
    priority: "high"
    needs_retesting: false
    status_history:
      - working: false
        agent: "main"
        comment: "Comprehensive test shows valid GSTIN validation failing - returning FAILED status for valid data"
      - working: true
        agent: "testing"
        comment: "FIXED - Database column 'status' issue resolved, Level 1 validation now working correctly"

  - task: "Level 2 DSR Validation with Call Plans"
    implemented: true
    working: false
    file: "classes/EnhancedValidationEngine.php"
    stuck_count: 1
    priority: "high"
    needs_retesting: true
    status_history:
      - working: false
        agent: "main"
        comment: "DSR mismatch not creating actions, Call Plans not updating"

  - task: "Level 3 Product Family Validation"
    implemented: true
    working: false
    file: "classes/EnhancedValidationEngine.php"
    stuck_count: 1
    priority: "high"
    needs_retesting: true
    status_history:
      - working: false
        agent: "main"
        comment: "Product family validation not creating cross-sell or splitting actions"

  - task: "Opportunity Splitting Logic"
    implemented: true
    working: false
    file: "classes/EnhancedValidationEngine.php"
    stuck_count: 1
    priority: "high"
    needs_retesting: true
    status_history:
      - working: false
        agent: "main"
        comment: "Multi-product opportunity splitting not occurring - opportunities count unchanged"

  - task: "Up-Sell Detection (Tier Upgrade)"
    implemented: true
    working: false
    file: "classes/EnhancedValidationEngine.php"
    stuck_count: 1
    priority: "high"
    needs_retesting: true
    status_history:
      - working: false
        agent: "main"
        comment: "Tier upgrade from Mainstream to Premium not creating Up-Sell opportunities"

  - task: "Volume Discrepancy Tracking"
    implemented: true
    working: false
    file: "classes/EnhancedValidationEngine.php"
    stuck_count: 1
    priority: "high"
    needs_retesting: true
    status_history:
      - working: false
        agent: "main"
        comment: "Volume discrepancy not being detected for over-sale scenarios"

  - task: "Sales Returns Processing"
    implemented: true
    working: false
    file: "classes/SalesReturnProcessor.php"
    stuck_count: 1
    priority: "high"
    needs_retesting: true
    status_history:
      - working: false
        agent: "main"
        comment: "Full return not changing stage from Order to Suspect as expected"

  - task: "Database Setup and Schema"
    implemented: true
    working: true
    file: "sql/create_tables.sql"
    stuck_count: 0
    priority: "medium"
    needs_retesting: false
    status_history:
      - working: true
        agent: "main"
        comment: "Database tables created successfully, triggers installed"

frontend:
  - task: "No Frontend Components"
    implemented: false
    working: "NA"
    file: "NA"
    stuck_count: 0
    priority: "low"
    needs_retesting: false
    status_history:
      - working: "NA"
        agent: "main"
        comment: "System is backend-only PHP application with web interfaces"

metadata:
  created_by: "main_agent"
  version: "1.0"
  test_sequence: 1
  run_ui: false

test_plan:
  current_focus:
    - "Level 1 GSTIN Validation"
    - "Enhanced Validation Engine Core Logic" 
    - "Sales Returns Processing"
    - "Opportunity Splitting Logic"
  stuck_tasks:
    - "Level 1 GSTIN Validation"
    - "Enhanced Validation Engine Core Logic"
  test_all: true
  test_priority: "high_first"

agent_communication:
  - agent: "main"
    message: "Comprehensive test suite executed showing 18.8% success rate (3/16 tests passed). Major issues in core validation engine - most validation logic returning FAILED status for valid data. Database setup working correctly. Need backend testing agent to investigate EnhancedValidationEngine and SalesReturnProcessor classes."
  - agent: "testing"
    message: "MAJOR PROGRESS: Fixed critical database schema issues. Level 1 GSTIN validation now working correctly. Fixed 'status' column references in EnhancedValidationEngine and SalesReturnProcessor. Opportunity creation working. Still need to fix: Sales returns processing, volume discrepancy tracking, and database trigger compatibility in tests."