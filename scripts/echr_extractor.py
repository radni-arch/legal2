#!/usr/bin/env python3
"""
ECHR Extractor Bridge for Laravel
Wraps echr-extractor library for use from PHP
"""

import sys
import json
import argparse
from datetime import datetime

try:
    from echr_extractor import get_echr, get_echr_extra, get_nodes_edges
except ImportError:
    print(json.dumps({"error": "echr-extractor not installed. Run: pip install echr-extractor"}))
    sys.exit(1)


def extract_metadata(args):
    """Extract case metadata without full text"""
    df = get_echr(
        count=args.count,
        start_date=args.start_date,
        end_date=args.end_date,
        language=args.language.split(',') if args.language else ['ENG'],
        save_file='n',
        verbose=False,
    )

    return df.to_dict('records')


def extract_with_text(args):
    """Extract case metadata with full text"""
    df, texts = get_echr_extra(
        count=args.count,
        start_date=args.start_date,
        end_date=args.end_date,
        language=args.language.split(',') if args.language else ['ENG'],
        save_file='n',
        verbose=False,
        threads=args.threads,
    )

    # Merge texts with metadata
    records = df.to_dict('records')
    for i, record in enumerate(records):
        if i < len(texts):
            record['full_text'] = texts[i].get('text', '')

    return records


def extract_by_query(args):
    """Extract using custom query payload"""
    df = get_echr(
        query_payload=args.query,
        save_file='n',
        verbose=False,
    )

    return df.to_dict('records')


def extract_network(args):
    """Extract case citation network"""
    # First get metadata
    df = get_echr(
        count=args.count,
        save_file='n',
        verbose=False,
    )

    nodes, edges = get_nodes_edges(df=df, save_file='n')

    return {
        'nodes': nodes.to_dict('records'),
        'edges': edges.to_dict('records'),
    }


def main():
    parser = argparse.ArgumentParser(description='ECHR Extractor Bridge')
    subparsers = parser.add_subparsers(dest='command', required=True)

    # Metadata extraction
    meta_parser = subparsers.add_parser('metadata')
    meta_parser.add_argument('--count', type=int, default=100)
    meta_parser.add_argument('--start-date', default=None)
    meta_parser.add_argument('--end-date', default=None)
    meta_parser.add_argument('--language', default='ENG')

    # Full text extraction
    text_parser = subparsers.add_parser('fulltext')
    text_parser.add_argument('--count', type=int, default=100)
    text_parser.add_argument('--start-date', default=None)
    text_parser.add_argument('--end-date', default=None)
    text_parser.add_argument('--language', default='ENG')
    text_parser.add_argument('--threads', type=int, default=10)

    # Query extraction
    query_parser = subparsers.add_parser('query')
    query_parser.add_argument('--query', required=True)

    # Network extraction
    net_parser = subparsers.add_parser('network')
    net_parser.add_argument('--count', type=int, default=100)

    args = parser.parse_args()

    try:
        if args.command == 'metadata':
            result = extract_metadata(args)
        elif args.command == 'fulltext':
            result = extract_with_text(args)
        elif args.command == 'query':
            result = extract_by_query(args)
        elif args.command == 'network':
            result = extract_network(args)
        else:
            result = {"error": "Unknown command"}

        print(json.dumps(result, default=str, ensure_ascii=False))

    except Exception as e:
        print(json.dumps({"error": str(e)}))
        sys.exit(1)


if __name__ == '__main__':
    main()
