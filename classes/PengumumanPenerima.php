<?php
class PengumumanPenerima {
  private $pdo;
  public function __construct(PDO $pdo){ $this->pdo = $pdo; }

  public function bulkInsert(int $pengumuman_id, array $rows): void {
    $sql = "INSERT IGNORE INTO pengumuman_penerima
      (pengumuman_id, siswa_id, nama_siswa, recipient_phone_number, kelas_id, kelas_nama, status)
      VALUES (:pid,:siswa_id,:nama_siswa,:phone,:kelas_id,:kelas_nama,'queued')";
    $st = $this->pdo->prepare($sql);
    foreach ($rows as $r) {
      $st->execute([
        ':pid'=>$pengumuman_id,
        ':siswa_id'=>$r['siswa_id'] ?? null,
        ':nama_siswa'=>$r['nama_siswa'] ?? null,
        ':phone'=>$r['recipient_phone_number'],
        ':kelas_id'=>$r['kelas_id'] ?? null,
        ':kelas_nama'=>$r['kelas_nama'] ?? null,
      ]);
    }
  }

  public function updateFromQueueResult(int $pengumuman_id, string $phone, string $status, ?string $err=null): void {
    $sql = "UPDATE pengumuman_penerima
            SET status=:status, attempt_count=attempt_count+1, last_attempt_at=NOW(),
                processed_at = CASE WHEN :status IN ('sent','failed') THEN NOW() ELSE processed_at END,
                error_message = :err
            WHERE pengumuman_id=:pid AND recipient_phone_number=:phone";
    $st = $this->pdo->prepare($sql);
    $st->execute([':status'=>$status, ':err'=>$err, ':pid'=>$pengumuman_id, ':phone'=>$phone]);
  }

  public function aggregateStatus(int $pengumuman_id): array {
    $sql = "SELECT
              SUM(status='queued') as queued,
              SUM(status='processing') as processing,
              SUM(status='sent') as sent,
              SUM(status='failed') as failed,
              COUNT(*) as total
            FROM pengumuman_penerima WHERE pengumuman_id=:pid";
    $st = $this->pdo->prepare($sql);
    $st->execute([':pid'=>$pengumuman_id]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: [];
  }
}
