#!/usr/bin/env python3
"""
Node2Vec Graph Embeddings Training Script (Sprint 4.5)

This script:
1. Connects to Neo4j and exports the decision citation graph
2. Converts Neo4j graph to NetworkX format
3. Trains Node2Vec model (128 dimensions)
4. Stores embeddings in PostgreSQL decision_graph_embeddings table

Usage:
    python scripts/train_graph_embeddings.py --neo4j-uri bolt://localhost:7687 \
                                              --neo4j-password password \
                                              --pg-host localhost \
                                              --pg-database dbname \
                                              --pg-user user \
                                              --pg-password password

Requirements:
    pip install neo4j networkx node2vec psycopg2-binary numpy

Node2Vec Parameters:
    - dimensions: 128 (embedding size)
    - walk_length: 80 (random walk length)
    - num_walks: 10 (walks per node)
    - workers: 4 (parallel workers)
    - p: 1 (return parameter)
    - q: 1 (in-out parameter)
"""

import argparse
import sys
from datetime import datetime
from typing import Dict, List, Tuple

try:
    import networkx as nx
    import numpy as np
    import psycopg2
    from neo4j import GraphDatabase
    from node2vec import Node2Vec
except ImportError as e:
    print(f"Error: Missing required Python package: {e}")
    print("\nInstall dependencies with:")
    print("pip install neo4j networkx node2vec psycopg2-binary numpy")
    sys.exit(1)


class GraphEmbeddingTrainer:
    """Trains Node2Vec embeddings for legal citation graph"""

    def __init__(
        self,
        neo4j_uri: str,
        neo4j_user: str,
        neo4j_password: str,
        pg_host: str,
        pg_database: str,
        pg_user: str,
        pg_password: str,
    ):
        self.neo4j_uri = neo4j_uri
        self.neo4j_user = neo4j_user
        self.neo4j_password = neo4j_password
        self.pg_conn_str = f"host={pg_host} dbname={pg_database} user={pg_user} password={pg_password}"
        self.model_version = "node2vec-v1.0"

    def export_graph_from_neo4j(self) -> nx.DiGraph:
        """
        Export decision citation graph from Neo4j to NetworkX

        Returns:
            NetworkX DiGraph with decision nodes and CITES edges
        """
        print("📊 Exporting graph from Neo4j...")

        driver = GraphDatabase.driver(
            self.neo4j_uri, auth=(self.neo4j_user, self.neo4j_password)
        )

        G = nx.DiGraph()

        with driver.session() as session:
            # Query: Get all Decision nodes
            result = session.run(
                """
                MATCH (d:Decision)
                RETURN d.id AS id, d.case_number AS case_number
                """
            )

            nodes_added = 0
            for record in result:
                G.add_node(
                    record["id"], case_number=record["case_number"] or "Unknown"
                )
                nodes_added += 1

            print(f"  ✓ Added {nodes_added} decision nodes")

            # Query: Get all CITES relationships between decisions
            result = session.run(
                """
                MATCH (d1:Decision)-[r:CITES]->(d2:Decision)
                RETURN d1.id AS source, d2.id AS target
                """
            )

            edges_added = 0
            for record in result:
                G.add_edge(record["source"], record["target"])
                edges_added += 1

            print(f"  ✓ Added {edges_added} citation edges")

        driver.close()

        print(
            f"  ✓ Graph exported: {G.number_of_nodes()} nodes, {G.number_of_edges()} edges"
        )

        return G

    def train_node2vec(self, G: nx.DiGraph) -> Dict[str, np.ndarray]:
        """
        Train Node2Vec model on citation graph

        Args:
            G: NetworkX graph of decisions and citations

        Returns:
            Dictionary mapping decision_id -> embedding vector (128-dim)
        """
        print("\n🤖 Training Node2Vec model...")

        if G.number_of_nodes() == 0:
            raise ValueError("Cannot train on empty graph")

        # Node2Vec parameters (tuned for legal citation graphs)
        node2vec = Node2Vec(
            G,
            dimensions=128,  # Embedding dimension
            walk_length=80,  # Length of random walks
            num_walks=10,  # Number of walks per node
            workers=4,  # Parallel workers
            p=1,  # Return parameter (controls likelihood of revisiting node)
            q=1,  # In-out parameter (controls exploration vs. exploitation)
        )

        print("  ⏳ Training (this may take several minutes)...")

        # Train model (using Word2Vec underneath)
        model = node2vec.fit(
            window=10,  # Context window size
            min_count=1,  # Ignore words with frequency < min_count
            batch_words=4,  # Batch size for training
        )

        print("  ✓ Model trained successfully")

        # Extract embeddings for all nodes
        embeddings = {}
        for node in G.nodes():
            embeddings[node] = model.wv[node]

        print(f"  ✓ Generated embeddings for {len(embeddings)} decisions")

        return embeddings

    def store_embeddings_in_postgres(self, embeddings: Dict[str, np.ndarray]):
        """
        Store embeddings in PostgreSQL decision_graph_embeddings table

        Args:
            embeddings: Dictionary mapping decision_id -> embedding vector
        """
        print("\n💾 Storing embeddings in PostgreSQL...")

        conn = psycopg2.connect(self.pg_conn_str)
        cursor = conn.cursor()

        trained_at = datetime.now()
        stored_count = 0

        for decision_id, embedding in embeddings.items():
            # Convert numpy array to PostgreSQL vector format
            embedding_str = "[" + ",".join(map(str, embedding)) + "]"

            cursor.execute(
                """
                INSERT INTO decision_graph_embeddings (decision_id, graph_embedding, trained_at, model_version, created_at, updated_at)
                VALUES (%s, %s, %s, %s, %s, %s)
                ON CONFLICT (decision_id) DO UPDATE
                SET graph_embedding = EXCLUDED.graph_embedding,
                    trained_at = EXCLUDED.trained_at,
                    model_version = EXCLUDED.model_version,
                    updated_at = EXCLUDED.updated_at
                """,
                (
                    decision_id,
                    embedding_str,
                    trained_at,
                    self.model_version,
                    trained_at,
                    trained_at,
                ),
            )

            stored_count += 1

            if stored_count % 100 == 0:
                print(f"  ⏳ Stored {stored_count}/{len(embeddings)} embeddings...")

        conn.commit()
        cursor.close()
        conn.close()

        print(f"  ✓ Stored {stored_count} embeddings in database")

    def run(self):
        """Execute complete training pipeline"""
        print("=" * 60)
        print("🚀 Node2Vec Graph Embeddings Training")
        print("=" * 60)

        try:
            # Step 1: Export graph from Neo4j
            G = self.export_graph_from_neo4j()

            if G.number_of_nodes() == 0:
                print("\n⚠️  Warning: No decision nodes found in Neo4j graph")
                print("Make sure decisions have been synced to Neo4j first")
                return False

            # Step 2: Train Node2Vec model
            embeddings = self.train_node2vec(G)

            # Step 3: Store embeddings in PostgreSQL
            self.store_embeddings_in_postgres(embeddings)

            print("\n" + "=" * 60)
            print("✅ Training completed successfully!")
            print("=" * 60)
            print(f"  • Nodes processed: {len(embeddings)}")
            print(f"  • Embedding dimension: 128")
            print(f"  • Model version: {self.model_version}")
            print("=" * 60)

            return True

        except Exception as e:
            print(f"\n❌ Error during training: {e}")
            import traceback

            traceback.print_exc()
            return False


def main():
    parser = argparse.ArgumentParser(
        description="Train Node2Vec embeddings for legal citation graph"
    )

    # Neo4j connection
    parser.add_argument(
        "--neo4j-uri", default="bolt://localhost:7687", help="Neo4j connection URI"
    )
    parser.add_argument("--neo4j-user", default="neo4j", help="Neo4j username")
    parser.add_argument("--neo4j-password", required=True, help="Neo4j password")

    # PostgreSQL connection
    parser.add_argument("--pg-host", default="localhost", help="PostgreSQL host")
    parser.add_argument(
        "--pg-database", default="ai_legal_war_machine", help="PostgreSQL database"
    )
    parser.add_argument("--pg-user", default="postgres", help="PostgreSQL user")
    parser.add_argument("--pg-password", required=True, help="PostgreSQL password")

    args = parser.parse_args()

    trainer = GraphEmbeddingTrainer(
        neo4j_uri=args.neo4j_uri,
        neo4j_user=args.neo4j_user,
        neo4j_password=args.neo4j_password,
        pg_host=args.pg_host,
        pg_database=args.pg_database,
        pg_user=args.pg_user,
        pg_password=args.pg_password,
    )

    success = trainer.run()
    sys.exit(0 if success else 1)


if __name__ == "__main__":
    main()
