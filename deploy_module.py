import sys
import os
import tarfile
import paramiko

HOST = "192.168.174.130"
USER = "root"
PASS = "admin123.."
MODULE_DIR = r"d:\study\zabbix\HostBatchClone"
TAR_FILE = r"d:\study\zabbix\HostBatchClone-deploy.tar.gz"

def package():
    print(f"[1/4] Packaging HostBatchClone to {TAR_FILE}...")
    with tarfile.open(TAR_FILE, "w:gz") as tar:
        tar.add(MODULE_DIR, arcname="HostBatchClone")
    size = os.path.getsize(TAR_FILE)
    print(f"  Package size: {size / 1024:.1f} KB")
    return True

def ssh_exec(ssh, cmd):
    stdin, stdout, stderr = ssh.exec_command(cmd)
    out = stdout.read().decode().strip()
    err = stderr.read().decode().strip()
    rc = stdout.channel.recv_exit_status()
    return rc, out, err

def scp_upload(ssh, local_path, remote_path):
    sftp = ssh.open_sftp()
    try:
        sftp.put(local_path, remote_path)
        print(f"  Uploaded: {local_path} -> {remote_path}")
    finally:
        sftp.close()

def main():
    # 1. Package
    package()

    # 2. SSH connect
    print(f"[2/4] Connecting to {USER}@{HOST}...")
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    ssh.connect(HOST, username=USER, password=PASS, timeout=30)
    print("  Connected")

    # 3. Upload
    print("[3/4] Uploading tarball...")
    scp_upload(ssh, TAR_FILE, "/tmp/HostBatchClone-deploy.tar.gz")

    # 4. Detect modules dir
    print("[4/4] Detecting Zabbix modules path...")
    modules_dir = None
    for p in ["/usr/share/zabbix/ui/modules", "/usr/share/zabbix/modules"]:
        rc, out, err = ssh_exec(ssh, f"ls -d {p} 2>/dev/null")
        if rc == 0 and out.strip():
            modules_dir = out.strip()
            print(f"  Found: {modules_dir}")
            break

    if not modules_dir:
        rc, out, err = ssh_exec(ssh, "find /usr/share/zabbix -maxdepth 2 -name modules -type d 2>/dev/null | head -1")
        if rc == 0 and out.strip():
            modules_dir = out.strip()
            print(f"  Found via find: {modules_dir}")

    if not modules_dir:
        print("  ERROR: Cannot find Zabbix modules directory")
        ssh.close()
        sys.exit(1)

    # Check Zabbix version
    rc, out, err = ssh_exec(ssh, "zabbix_server -V 2>/dev/null | head -1")
    if rc == 0 and out.strip():
        print(f"  Zabbix version: {out.strip()}")

    # 5. Deploy
    print(f"  Deploying to {modules_dir}/HostBatchClone/ ...")
    
    deploy_script = f"""
set -e
tar -xzf /tmp/HostBatchClone-deploy.tar.gz -C {modules_dir}/
if id nginx >/dev/null 2>&1; then
    chown -R nginx:nginx {modules_dir}/HostBatchClone/
elif id www-data >/dev/null 2>&1; then
    chown -R www-data:www-data {modules_dir}/HostBatchClone/
fi
systemctl reload php-fpm 2>/dev/null || systemctl reload php81-php-fpm 2>/dev/null || systemctl reload php82-php-fpm 2>/dev/null || true
rm -f /tmp/HostBatchClone-deploy.tar.gz
ls {modules_dir}/HostBatchClone/manifest.json
echo DEPLOY_SUCCESS
"""
    rc, out, err = ssh_exec(ssh, deploy_script)
    if rc == 0 and "DEPLOY_SUCCESS" in out:
        print("  ✅ Module deployed successfully!")
        # Show file list
        rc2, out2, err2 = ssh_exec(ssh, f"ls -la {modules_dir}/HostBatchClone/")
        if rc2 == 0:
            print(f"\n  Module directory contents:")
            for line in out2.strip().split("\n"):
                print(f"    {line}")
    else:
        print(f"  Deploy failed: rc={rc}")
        print(f"  stdout: {out}")
        print(f"  stderr: {err}")
        ssh.close()
        sys.exit(1)

    ssh.close()
    print(f"\n✅ All done! Module deployed to {modules_dir}/HostBatchClone/")
    print("   Now enable it in Zabbix UI: Administration → General → Modules → Scan directory")

if __name__ == "__main__":
    main()
