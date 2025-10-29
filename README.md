<h1 align="center">🛡️ Security Implementation Document for AWS Projects</h1>

---

<h2>📘 Overview</h2>

<p>This document provides a comprehensive guide to implement essential <b>security configurations</b> in AWS-based application environments.</p>

<p>It is designed for both <b>DevOps</b> and <b>Development</b> teams to standardize security best practices across servers, databases, and CDN configurations.</p>

---

<h2>🎯 Objectives</h2>

<ul>
<li>Enable and centralize application access logging.</li>
<li>Secure and monitor database access.</li>
<li>Implement least privilege user principles.</li>
<li>Protect sensitive credentials using AWS Secrets Manager.</li>
<li>Enforce restricted access to CDN assets.</li>
<li>Remove hardcoded keys and secrets from code/configuration files.</li>
</ul>

---

<h2>✅ Security Implementation Checklist</h2>

<table>
<thead>
<tr>
<th>No</th>
<th>Security Measure</th>
<th>Description</th>
</tr>
</thead>
<tbody>
<tr><td>1</td><td>Enable public IP logging in web servers</td><td>Capture end-user IP addresses in Apache/Nginx access logs</td></tr>
<tr><td>2</td><td>Enable RDS logging to CloudWatch</td><td>Monitor database access and query activities</td></tr>
<tr><td>3</td><td>Implement least privilege access</td><td>Create limited database users for applications</td></tr>
<tr><td>4</td><td>Use AWS Secrets Manager</td><td>Replace hardcoded database credentials</td></tr>
<tr><td>5</td><td>Restrict CDN access</td><td>Configure signed cookies/keys for CloudFront</td></tr>
<tr><td>6</td><td>Remove hardcoded access keys</td><td>Remove all keys from code/config and use IAM roles</td></tr>
</tbody>
</table>

---

<h2>🔧 Implementation Details</h2>

<h3>1️⃣ Enable Public IP Logging in Web Servers</h3>

<h4>Apache (httpd) Configuration</h4>

<b>File Path:</b> <code>/etc/httpd/conf.d/&lt;domain&gt;.conf</code>

<pre><code>&lt;VirtualHost *:80&gt;
    DocumentRoot "/var/www/recruitment/api/web"
    ServerName recruitmentapi.nios.ac.in

    # Capture Client Public IP
    RemoteIPHeader X-Forwarded-For
    RemoteIPTrustedProxy 10.0.0.0/16

    # Logging Format
    LogFormat "%a %l %u %t \"%r\" %&gt;s %b" client_ip_combined
    CustomLog /var/log/httpd/live_access.log client_ip_combined

    SetEnv SECRET_VOULT recruitment-db
    SetEnv AWS_REGION ap-south-1
    SetEnv REDIS_HOST projects-redis-001.fph39r.0001.aps1.cache.amazonaws.com

    &lt;IfModule mpm_event_module&gt;
        ProxyPassMatch ^/(.*\.php(/.*)?)$ fcgi://127.0.0.1:9000/var/www/recruitment/api/web timeout=300
    &lt;/IfModule&gt;

    &lt;Directory "/var/www/recruitment/api/web"&gt;
        AllowOverride All
    &lt;/Directory&gt;
&lt;/VirtualHost&gt;
</code></pre>

**Verify logs:**
<pre><code>tail -f /var/log/httpd/live_access.log
</code></pre>

---

<h4>Nginx Configuration</h4>

<b>File Path:</b> <code>/etc/nginx/conf.d/&lt;domain&gt;.conf</code>

<pre><code>server {
    listen 80;
    server_name recruitmentapi.nios.ac.in;
    root /var/www/recruitment/api/web;

    # Capture Client IP
    real_ip_header X-Forwarded-For;
    set_real_ip_from 10.0.0.0/16;

    access_log /var/log/nginx/live_access.log main;

    location / {
        try_files $uri $uri/ /index.php?$args;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
</code></pre>

**Verify logs:**
<pre><code>tail -f /var/log/nginx/live_access.log
</code></pre>

---

<h3>2️⃣ Enable RDS Logs and Send to CloudWatch</h3>

<ol>
<li>Go to <b>AWS Console → RDS → Parameter Groups</b></li>
<li>Create a new <b>DB Cluster Parameter Group</b></li>
<li>Set these parameters:</li>
</ol>

<table>
<tr><th>Parameter</th><th>Value</th></tr>
<tr><td>general_log</td><td>1</td></tr>
<tr><td>slow_query_log</td><td>1</td></tr>
<tr><td>log_output</td><td>FILE</td></tr>
<tr><td>log_error_verbosity</td><td>3</td></tr>
<tr><td>log_queries_not_using_indexes</td><td>1</td></tr>
</table>

<p>Attach this parameter group → Reboot RDS → Enable CloudWatch export.</p>

---

<h3>3️⃣ Implement Least Privilege Database User</h3>

<pre><code>CREATE USER 'nios_rectt_dev'@'%' IDENTIFIED BY 'StrongPassword!';
GRANT SELECT, INSERT, UPDATE, DELETE ON recruitment_rewamp_v2.* TO 'nios_rectt_dev'@'%';
FLUSH PRIVILEGES;
</code></pre>

Use this user with AWS Secrets Manager and remove admin users from configs.

---

<h3>4️⃣ Implement AWS Secrets Manager</h3>

<b>Step 1 – Create Secret</b>

<table>
<tr><th>Key</th><th>Value</th></tr>
<tr><td>host</td><td>projects-cluster.cluster-cxxvfg4pfd5f.ap-south-1.rds.amazonaws.com</td></tr>
<tr><td>username</td><td>nios_rectt_dev</td></tr>
<tr><td>password</td><td>klf7*J-buViwHN#</td></tr>
<tr><td>dbname</td><td>recruitment_rewamp_v2</td></tr>
<tr><td>port</td><td>3306</td></tr>
</table>

Secret Name: <code>recruitment-db</code>

---

<b>Step 2 – Integrate with App</b>

<pre><code>cd /var/www/recruitment/common/components
git clone https://github.com/Insphere-Suhail/AWS-Files.git
</code></pre>

Ensure files exist:
<pre><code>common/components/ConfigBootstrap.php
common/components/SecretManager.php
</code></pre>

Update `common/config/main.php`:
<pre><code>'bootstrap' =&gt; ['configBootstrap'],
'components' =&gt; [
  'configBootstrap' =&gt; ['class' =&gt; 'common\components\ConfigBootstrap']
],
</code></pre>

Apache vHost:
<pre><code>SetEnv SECRET_VOULT recruitment-db
SetEnv AWS_REGION ap-south-1
SetEnv REDIS_HOST projects-redis-001.fph39r.0001.aps1.cache.amazonaws.com
</code></pre>

Remove DB credentials:
<pre><code>'db' =&gt; ['class' =&gt; 'yii\db\Connection'],
</code></pre>

Attach IAM role to EC2 and deploy updates.

---

<h3>5️⃣ Restrict CDN Access (CloudFront Signed Cookies)</h3>

<b>Generate PEM Files:</b>

<pre><code>cd ~/Downloads
openssl genrsa -out voc.pem 2048
openssl rsa -in voc.pem -pubout -out voc.pub.pem
</code></pre>

<b>CloudFront Setup:</b>
<ol>
<li>AWS Console → CloudFront → Public Keys → Add Key</li>
<li>Create Key Group → Attach Public Key</li>
<li>Edit Distribution → Behavior → Restrict Viewer Access → Enable</li>
</ol>

<b>Application Setup:</b>
<pre><code>'cloudFrontService' =&gt; [
  'class' =&gt; 'common\components\CloudFrontService',
],
</code></pre>

Private Key Path: <code>/var/www/recruitment/common/cloudfront_key/recruitment-nios.pem</code>

Update params.php:
<pre><code>'awsCloudfront' =&gt; [
 'default' =&gt; [
   'distribution_url' =&gt; 'https://d1l7no631hft7n.cloudfront.net',
   'region' =&gt; 'ap-south-1',
   'private_key_path' =&gt; Yii::getAlias('@common/cloudfront_key/recruitment-nios.pem'),
   'key_pair_id' =&gt; 'K25KYUOGTBCUY8',
   'cookie_domain' =&gt; '.nios.ac.in',
 ],
],
</code></pre>

Frontend:
<pre><code>Yii::$app->cloudFrontService->setSignedCookie('default');
</code></pre>

---

<h3>6️⃣ Remove Access Keys from Configs</h3>

Edit <code>/var/www/recruitment/common/config/params-local.php</code> and remove:
<pre><code>'aws_access_key' =&gt; 'AKIA********',
'aws_secret_key' =&gt; '*************',
</code></pre>

Push sanitized version and ensure EC2 IAM role access.

---

<h2>🧪 Validation Checklist</h2>

<table>
<tr><th>Checkpoint</th><th>Validation</th></tr>
<tr><td>Web Logs</td><td>Check /var/log/httpd or /var/log/nginx logs</td></tr>
<tr><td>RDS Logs</td><td>Verify CloudWatch stream</td></tr>
<tr><td>DB Connection</td><td>Confirm via Secrets Manager</td></tr>
<tr><td>CDN Restriction</td><td>Access without signed cookie should fail</td></tr>
<tr><td>IAM Role Access</td><td>Check S3 &amp; Secrets access from EC2</td></tr>
</table>

---

<h2>📦 Reference Repository</h2>

<p><a href="https://github.com/Insphere-Suhail/AWS-Files.git" target="_blank">https://github.com/Insphere-Suhail/AWS-Files.git</a></p>

---

<h2>🏁 Summary</h2>

<ul>
<li>All credentials secured via AWS Secrets Manager</li>
<li>Centralized logging via CloudWatch</li>
<li>CDN access protected with signed cookies</li>
<li>No static keys remain in configuration</li>
</ul>

<p><b>This completes the AWS Security Implementation for your project.</b></p>
