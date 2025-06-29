<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-md">
        <h2 class="text-2xl font-bold mb-6 text-center text-gray-800">Reset Password</h2>
        
        @if(session('error'))
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                {{ session('error') }}
            </div>
        @endif

        @if(session('success'))
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                {{ session('success') }}
            </div>
        @endif

        <form id="resetForm" class="space-y-4">
            <input type="hidden" id="token" value="{{ request('token') }}">
            <input type="hidden" id="email" value="{{ request('email') }}">
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                <input type="email" value="{{ request('email') }}" disabled 
                       class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">New Password</label>
                <input type="password" id="password" required minlength="8"
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Confirm Password</label>
                <input type="password" id="password_confirmation" required minlength="8"
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>

            <button type="submit" 
                    class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                Reset Password
            </button>
        </form>

        <div class="mt-6 text-center">
            <a href="#" class="text-sm text-blue-600 hover:underline">Back to Login</a>
        </div>
    </div>

    <script>
        document.getElementById('resetForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const token = document.getElementById('token').value;
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            const password_confirmation = document.getElementById('password_confirmation').value;

            if (password !== password_confirmation) {
                alert('Passwords do not match');
                return;
            }

            try {
                const response = await fetch('/api/auth/reset-password', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        token,
                        email,
                        password,
                        password_confirmation
                    })
                });

                const data = await response.json();

                if (response.ok) {
                    alert('Password reset successfully! You can now login with your new password.');
                    window.location.href = '/';
                } else {
                    alert(data.message || 'Error resetting password');
                }
            } catch (error) {
                alert('Network error. Please try again.');
            }
        });
    </script>
</body>
</html>