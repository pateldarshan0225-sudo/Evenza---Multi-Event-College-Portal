"""Only invalid POST requests: missing required fields always prevent INSERTs."""
import re
import urllib.request
import urllib.parse

url = 'http://localhost/Evenza/Frontend/register_college.php'
cases = [
    ({}, ['College name is required.', 'Please select an existing university']),
    ({'name[]': 'bad', 'email[]': 'bad'}, ['Please enter valid registration details.']),
    ({'name': 'Invalid test college', 'slug': 'BAD SLUG!', 'university_id': '-1'}, ['Use lowercase letters', 'Please select an existing university']),
    ({'phone': '1234567890', 'password': '123'}, ['valid 10-digit Indian', 'Password must be at least 6 characters.']),
]
for fields, messages in cases:
    request = urllib.request.Request(url, data=urllib.parse.urlencode(fields).encode())
    with urllib.request.urlopen(request) as response:
        html = response.read().decode()
        assert not re.search(r'Fatal error|Warning:|Parse error', html), 'PHP runtime error'
        assert response.url.endswith('register_college.php'), 'Unexpected success redirect'
        for message in messages:
            assert message in html, message
boundary = 'evenza-invalid-upload-test'
data = (f'--{boundary}\r\nContent-Disposition: form-data; name="logo"; filename="fake.png"\r\nContent-Type: image/png\r\n\r\nnot-an-image\r\n--{boundary}--\r\n').encode()
request = urllib.request.Request(url, data=data, headers={'Content-Type': f'multipart/form-data; boundary={boundary}'})
with urllib.request.urlopen(request) as response:
    html = response.read().decode()
    assert 'Please choose a valid JPG, PNG or WEBP image.' in html
    assert not re.search(r'Fatal error|Warning:|Parse error', html)
print(f'{len(cases) + 1} invalid POST/upload cases passed; no valid registration submitted.')
