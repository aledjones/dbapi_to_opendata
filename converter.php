<?php

class converter
{
    const BASE_URL = "https://api.deutschebahn.com/fahrplan-plus/v1/";

    public function get_departure($access_token = "", $station_id, $date)
    {
        if (empty($access_token))
            throw new ErrorException("Access token cannot be empty!");

        else
            $request = \Httpful\Request::get(converter::BASE_URL . "departureBoard/$station_id?date=$date")
                ->addHeader('Authorization', 'Bearer ' . $access_token)
                ->send();
        return $request;
    }

    public function get_station_id_by_name($access_token = "", $station_name)
    {
        if (empty($access_token))
            throw new ErrorException("Access token cannot be empty!");
        else
            $request = \Httpful\Request::get(converter::BASE_URL . "location/" . str_replace(" ", "%20", $station_name))
                ->addHeader('Authorization', 'Bearer ' . $access_token)
                ->send();
        $request = json_decode($request, true);
        return $request[0]['id'];
    }
}