function Aktivita(props) {
  let aktivita = props.aktivita;
  let kapacita;
  //TODO: dořešit jaké jsou přesně možnosti a aby server vracel kapacity skutečně v podobě, kterou chceme
  if(aktivita.kapacita_u > 0) {
    kapacita = <span>({aktivita.prihlaseno_f+aktivita.prihlaseno_m}/{aktivita.kapacita_u})</span>;
  }
  else if(aktivita.kapacita_m > 0 && aktivita.kapacita_f > 0) {
    kapacita = <span>({aktivita.prihlaseno_f}/{aktivita.kapacita_f})({aktivita.prihlaseno_m}/{aktivita.kapacita_m})</span>;
  }
  else if(aktivita.kapacita_m > 0 && aktivita.kapacita_f == 0) {
    kapacita = <span>({aktivita.prihlaseno_m}/{aktivita.kapacita_m})</span>;
  }
  else if(aktivita.kapacita_m == 0 && aktivita.kapacita_f > 0) {
    kapacita = <span>({aktivita.prihlaseno_f}/{aktivita.kapacita_f})</span>;
  }

  return (
    <td colSpan={aktivita.delka} className = "tabulka-aktivita" onClick = {() => props.zvolTutoAktivitu(aktivita)}>
      <span>{aktivita.nazev}</span>
      <br/>
      {kapacita}
    </td>
  );
}
