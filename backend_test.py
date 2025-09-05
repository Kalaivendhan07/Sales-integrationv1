#!/usr/bin/env python3
"""
Backend Testing Suite for FastAPI/MongoDB System
Tests the actual running backend system based on test_result.md requirements
"""

import requests
import json
import time
import sys
from datetime import datetime
import uuid

class BackendTester:
    def __init__(self):
        # Get backend URL from frontend .env file
        try:
            with open('/app/frontend/.env', 'r') as f:
                env_content = f.read()
                for line in env_content.split('\n'):
                    if line.startswith('REACT_APP_BACKEND_URL='):
                        self.base_url = line.split('=')[1].strip()
                        break
                else:
                    raise ValueError("REACT_APP_BACKEND_URL not found in frontend/.env")
        except Exception as e:
            print(f"❌ Error reading backend URL: {e}")
            sys.exit(1)
        
        self.api_url = f"{self.base_url}/api"
        self.test_results = []
        
        print(f"🔗 Testing backend at: {self.api_url}")
        print("=" * 80)
    
    def log_test(self, test_name, success, message=""):
        """Log test result"""
        status = "✅ PASS" if success else "❌ FAIL"
        print(f"{status} {test_name}")
        if message:
            print(f"   {message}")
        
        self.test_results.append({
            'test': test_name,
            'success': success,
            'message': message,
            'timestamp': datetime.now().isoformat()
        })
    
    def test_root_endpoint(self):
        """Test the root API endpoint"""
        try:
            response = requests.get(f"{self.api_url}/", timeout=10)
            success = response.status_code == 200
            
            if success:
                data = response.json()
                message = f"Response: {data.get('message', 'No message')}"
            else:
                message = f"Status: {response.status_code}"
            
            self.log_test("Root Endpoint (/api/)", success, message)
            return success
            
        except Exception as e:
            self.log_test("Root Endpoint (/api/)", False, f"Error: {str(e)}")
            return False
    
    def test_status_creation(self):
        """Test creating a status check record"""
        try:
            test_data = {
                "client_name": f"Test Client {int(time.time())}"
            }
            
            response = requests.post(
                f"{self.api_url}/status", 
                json=test_data,
                headers={'Content-Type': 'application/json'},
                timeout=10
            )
            
            success = response.status_code == 200
            
            if success:
                data = response.json()
                message = f"Created status for: {data.get('client_name')}, ID: {data.get('id')}"
            else:
                message = f"Status: {response.status_code}, Response: {response.text[:100]}"
            
            self.log_test("Status Creation (POST /api/status)", success, message)
            return success, data if success else None
            
        except Exception as e:
            self.log_test("Status Creation (POST /api/status)", False, f"Error: {str(e)}")
            return False, None
    
    def test_status_retrieval(self):
        """Test retrieving status check records"""
        try:
            response = requests.get(f"{self.api_url}/status", timeout=10)
            success = response.status_code == 200
            
            if success:
                data = response.json()
                message = f"Retrieved {len(data)} status records"
            else:
                message = f"Status: {response.status_code}"
            
            self.log_test("Status Retrieval (GET /api/status)", success, message)
            return success, data if success else None
            
        except Exception as e:
            self.log_test("Status Retrieval (GET /api/status)", False, f"Error: {str(e)}")
            return False, None
    
    def test_database_connectivity(self):
        """Test database operations by creating and retrieving records"""
        try:
            # Create a test record
            create_success, created_record = self.test_status_creation()
            if not create_success:
                self.log_test("Database Connectivity", False, "Failed to create test record")
                return False
            
            # Retrieve records to verify database read
            retrieve_success, records = self.test_status_retrieval()
            if not retrieve_success:
                self.log_test("Database Connectivity", False, "Failed to retrieve records")
                return False
            
            # Verify our created record exists
            created_id = created_record.get('id')
            found_record = any(record.get('id') == created_id for record in records)
            
            if found_record:
                self.log_test("Database Connectivity", True, f"Successfully created and retrieved record with ID: {created_id}")
                return True
            else:
                self.log_test("Database Connectivity", False, "Created record not found in retrieval")
                return False
                
        except Exception as e:
            self.log_test("Database Connectivity", False, f"Error: {str(e)}")
            return False
    
    def test_api_validation(self):
        """Test API input validation"""
        try:
            # Test with invalid data (missing required field)
            response = requests.post(
                f"{self.api_url}/status", 
                json={},  # Empty payload
                headers={'Content-Type': 'application/json'},
                timeout=10
            )
            
            # Should return 422 for validation error
            success = response.status_code == 422
            
            if success:
                message = "Correctly rejected invalid input with 422 status"
            else:
                message = f"Expected 422, got {response.status_code}"
            
            self.log_test("API Input Validation", success, message)
            return success
            
        except Exception as e:
            self.log_test("API Input Validation", False, f"Error: {str(e)}")
            return False
    
    def test_cors_headers(self):
        """Test CORS configuration"""
        try:
            response = requests.options(f"{self.api_url}/status", timeout=10)
            
            # Check for CORS headers
            cors_headers = [
                'access-control-allow-origin',
                'access-control-allow-methods',
                'access-control-allow-headers'
            ]
            
            found_headers = []
            for header in cors_headers:
                if header in response.headers:
                    found_headers.append(header)
            
            success = len(found_headers) >= 2  # At least 2 CORS headers should be present
            
            if success:
                message = f"CORS headers found: {', '.join(found_headers)}"
            else:
                message = f"Missing CORS headers. Found: {', '.join(found_headers)}"
            
            self.log_test("CORS Configuration", success, message)
            return success
            
        except Exception as e:
            self.log_test("CORS Configuration", False, f"Error: {str(e)}")
            return False
    
    def test_performance(self):
        """Test API response performance"""
        try:
            start_time = time.time()
            response = requests.get(f"{self.api_url}/", timeout=10)
            end_time = time.time()
            
            response_time = (end_time - start_time) * 1000  # Convert to milliseconds
            success = response.status_code == 200 and response_time < 2000  # Less than 2 seconds
            
            message = f"Response time: {response_time:.2f}ms"
            
            self.log_test("API Performance", success, message)
            return success
            
        except Exception as e:
            self.log_test("API Performance", False, f"Error: {str(e)}")
            return False
    
    def test_multiple_concurrent_requests(self):
        """Test handling multiple concurrent requests"""
        try:
            import threading
            import queue
            
            results_queue = queue.Queue()
            
            def make_request():
                try:
                    response = requests.get(f"{self.api_url}/", timeout=10)
                    results_queue.put(response.status_code == 200)
                except:
                    results_queue.put(False)
            
            # Create 5 concurrent threads
            threads = []
            for i in range(5):
                thread = threading.Thread(target=make_request)
                threads.append(thread)
                thread.start()
            
            # Wait for all threads to complete
            for thread in threads:
                thread.join()
            
            # Check results
            successful_requests = 0
            while not results_queue.empty():
                if results_queue.get():
                    successful_requests += 1
            
            success = successful_requests >= 4  # At least 4 out of 5 should succeed
            message = f"{successful_requests}/5 concurrent requests successful"
            
            self.log_test("Concurrent Request Handling", success, message)
            return success
            
        except Exception as e:
            self.log_test("Concurrent Request Handling", False, f"Error: {str(e)}")
            return False
    
    def run_all_tests(self):
        """Run all backend tests"""
        print("🚀 Starting Backend API Testing Suite")
        print("=" * 80)
        
        tests = [
            self.test_root_endpoint,
            self.test_status_creation,
            self.test_status_retrieval,
            self.test_database_connectivity,
            self.test_api_validation,
            self.test_cors_headers,
            self.test_performance,
            self.test_multiple_concurrent_requests
        ]
        
        passed = 0
        total = len(tests)
        
        for test in tests:
            try:
                if test():
                    passed += 1
            except Exception as e:
                print(f"❌ Test {test.__name__} failed with exception: {e}")
            
            print()  # Add spacing between tests
        
        # Generate summary
        print("=" * 80)
        print("🎯 BACKEND TESTING SUMMARY")
        print("=" * 80)
        
        success_rate = (passed / total) * 100
        print(f"📊 Tests Passed: {passed}/{total} ({success_rate:.1f}%)")
        
        if success_rate >= 80:
            print("✅ Backend system is functioning well")
        elif success_rate >= 60:
            print("⚠️  Backend system has some issues but is mostly functional")
        else:
            print("❌ Backend system has significant issues")
        
        print("\n📋 Individual Test Results:")
        for result in self.test_results:
            status = "✅" if result['success'] else "❌"
            print(f"  {status} {result['test']}")
            if result['message']:
                print(f"     {result['message']}")
        
        return success_rate >= 80

if __name__ == "__main__":
    tester = BackendTester()
    success = tester.run_all_tests()
    
    if success:
        print("\n🎉 All critical backend tests passed!")
        sys.exit(0)
    else:
        print("\n⚠️  Some backend tests failed - review required")
        sys.exit(1)