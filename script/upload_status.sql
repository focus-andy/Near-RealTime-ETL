create table upload_status_product_log(
partition bigint primary key not null,
pipelet_id bigint default 0
)engine = innodb ;
