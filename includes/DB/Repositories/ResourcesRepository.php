<?php
namespace ClassFlowPro\DB\Repositories;

if (!defined('ABSPATH')) { exit; }

class ResourcesRepository
{
    private \wpdb $db; private string $table;
    public function __construct(){ global $wpdb; $this->db=$wpdb; $this->table=$wpdb->prefix . 'cfp_resources'; }
    public function find(int $id): ?array { $r=$this->db->get_row($this->db->prepare("SELECT * FROM {$this->table} WHERE id=%d",$id),ARRAY_A); return $r?:null; }
    public function paginate(int $page=1,int $per_page=20,string $s=''): array {
        $off=max(0,($page-1)*$per_page); $w='WHERE 1=1';$p=[];
        if($s){$w.=' AND name LIKE %s';$p[]='%'.$this->db->esc_like($s).'%';}
        if ($p) { $total=(int)$this->db->get_var($this->db->prepare("SELECT COUNT(*) FROM {$this->table} {$w}",...$p)); }
        else { $total=(int)$this->db->get_var("SELECT COUNT(*) FROM {$this->table} {$w}"); }
        $items=$this->db->get_results($this->db->prepare("SELECT * FROM {$this->table} {$w} ORDER BY name ASC LIMIT %d OFFSET %d",...array_merge($p,[$per_page,$off])),ARRAY_A);
        return ['items'=>$items,'total'=>$total];
    }
    public function create(array $d): int { $row=wp_parse_args($d,['name'=>'','type'=>null,'capacity'=>null,'location_id'=>null]); $this->db->insert($this->table, array_merge($row,['created_at'=>current_time('mysql',true),'updated_at'=>current_time('mysql',true)]), ['%s','%s','%d','%d','%s','%s']); return (int)$this->db->insert_id; }
    public function update(int $id,array $d): bool { $f=[];$fm=[]; foreach(['name'=>'%s','type'=>'%s','capacity'=>'%d','location_id'=>'%d'] as $k=>$fmt){ if(array_key_exists($k,$d)){ $f[$k]=$d[$k]; $fm[]=$fmt; } } $f['updated_at']=current_time('mysql',true); $fm[]='%s'; return false!==$this->db->update($this->table,$f,['id'=>$id],$fm,['%d']); }
    public function delete(int $id): bool { return false!==$this->db->delete($this->table,['id'=>$id],['%d']); }
}
