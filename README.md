# Loan API Management

## Users

- `POST /users`: Register a new user.
  - **Example Input:**
    ```json
    {
      "username": "john_doe",
      "name": "john",
      "password": "secretpassword"
    }
    ```
  - **Example Output:**
    ```json
    {
        "data": {
            "id": 1,
            "username": "john_doe",
            "name": "jhon",
            "role": "user"
	    }
    }
    ```

- `POST /users/login`: Log in as an existing user.
  - **Example Input:**
    ```json
    {
      "username": "john_doe",
      "password": "secretpassword"
    }
    ```
  - **Example Output:**
    ```json
    {
      "id": 1,
      "username": "john_doe",
      "name": "jhon",
      "token": "3ba32cec-2540-450f-90bd-9b9c0a9672c2"
    }
    ```

## Authenticated User Routes

All routes below require authentication through the `ApiAuthMiddleware`.

### User Management

- `GET /users/current`: Get information about the current authenticated user.
  - **Example Output:**
    ```json
    {
        "data": {
            "id": 4,
            "username": "john_doe",
            "name": "jhon",
            "token": "3ba32cec-2540-450f-90bd-9b9c0a9672c2",
            "role": "user"
        }
    }
    ```

- `PATCH /users/current`: Update information for the current authenticated user.
  - **Example Input:**
    ```json
    {
      "name": "Updated Name"
    }
    ```
  - **Example Output:**
    ```json
    {
      "message": "User information updated successfully."
    }
    ```

- `DELETE /users/logout`: Log out the current authenticated user.
  - **Example Output:**
    ```json
    {
      "message": "User logged out successfully."
    }
    ```

### Loans

- `POST /loans`: Submit a new loan request.
  - **Example Input:**
    ```json
    {
      "amount": 5000,
      "term": 12
    }
    ```
  - **Example Output:**
    ```json
    {
        "data": {
            "id": 1,
            "amount": 5000,
            "term": 12,
            "due_date": "2023-01-01",
            "state": "PENDING",
        }
    }
    ```

- `GET /list-loans/{loanId?}`: List all loans or get details for a specific loan (optional: loanId parameter).
  - **Example Output (List All Loans):**
    ```json
    [
      {
        "data": {
            "id": 1,
            "amount": 5000,
            "term": 12,
            "due_date": "2022-02-01",
            "state": "PENDING",
        }
      },
      // ... other loans
    ]
    ```
  - **Example Output (Get Details for a Specific Loan):**
    ```json
    {
        "loan_details": {
            "id": 1,
            "amount": "5000",
            "term": 12,
            "due_date": "2024-01-24",
            "state": "APPROVED",
            "repayments": [
                {
                    "id": 1,
                    "loan_id": 1,
                    "amount": "416.67",
                    "due_date": "2024-01-31",
                    "state": "PENDING"
                },
                // ... other loans
            ]
        }
    }
    ```

- `POST /repay-loan/{loanId}`: Repay a loan.
  - **Example Input:**
    ```json
    {
      "repayment_amount": 1000
    }
    ```
  - **Example Output:**
    ```json
    {
      "loan_id": 1,
      "repayment": {
        // ... repayment details
      }
    }
    ```

### Loan Approval (Admin Only)

- `POST /loans/{loanId}/approve`: Approve a loan (Admin Only).
  - **Example Output:**
    ```json
    {
      "loan_id": 1,
      "user_id": 2,
      "message": "Loan approved successfully."
    }
    ```

- `GET /loans/{loanId}/details`: Get details of a loan, including repayments and approval history.
  - **Example Output:**
    ```json
    {
      "loan_details": {
        // ... loan details
        "approval_history": [
          // ... approval history details
        ],
        "repayments": [
          // ... repayment details
        ]
      }
    }
    ```
