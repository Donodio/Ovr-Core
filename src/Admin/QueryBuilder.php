<?php

namespace OVR\Admin;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class QueryBuilder {
    private string $type = 'post';   // 'post' | 'user' | 'term'
    private array $meta_queries  = [];
    private array $tax_queries   = [];
    private array $date_queries  = [];
    private array $base_args     = [];
    private array $query_vars    = [];

    public static function for_posts( array $base_args = [] ): self {
        $qb = new self();
        $qb->type      = 'post';
        $qb->base_args = $base_args;
        return $qb;
    }

    public static function for_users( array $base_args = [] ): self {
        $qb = new self();
        $qb->type      = 'user';
        $qb->base_args = $base_args;
        return $qb;
    }

    public function add_meta_query( array $clause ): self {
        $this->meta_queries[] = $clause;
        return $this;
    }

    public function add_tax_query( array $clause ): self {
        $this->tax_queries[] = $clause;
        return $this;
    }

    public function add_date_query( array $clause ): self {
        $this->date_queries[] = $clause;
        return $this;
    }

    public function set( string $key, $value ): self {
        $this->query_vars[ $key ] = $value;
        return $this;
    }

    public function get_args(): array {
        $args = array_merge( $this->base_args, $this->query_vars );
        if ( ! empty( $this->meta_queries ) ) {
            $args['meta_query'] = $this->normalize_relation( $this->meta_queries );
        }
        if ( ! empty( $this->tax_queries ) ) {
            $args['tax_query'] = $this->normalize_relation( $this->tax_queries );
        }
        if ( ! empty( $this->date_queries ) ) {
            $args['date_query'] = $this->normalize_relation( $this->date_queries );
        }
        return $args;
    }

    public function run(): \WP_Query|\WP_User_Query {
        if ( 'user' === $this->type ) {
            return new \WP_User_Query( $this->get_args() );
        }
        return new \WP_Query( $this->get_args() );
    }

    private function normalize_relation( array $clauses ): array {
        $has_relations = false;
        foreach ( $clauses as $c ) {
            if ( isset( $c['relation'] ) ) {
                $has_relations = true;
                break;
            }
        }
        if ( $has_relations ) {
            $normalized = [ 'relation' => 'AND' ];
            foreach ( $clauses as $c ) {
                $normalized[] = $c;
            }
            return $normalized;
        }
        $normalized = [ 'relation' => 'AND' ];
        foreach ( $clauses as $c ) {
            $normalized[] = $c;
        }
        return $normalized;
    }
}
