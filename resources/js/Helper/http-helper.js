import axios from "axios";

export function httpGet(url) {
    return axios.get(url, {
        headers: {
            "Content-Type": "application/json",
            Accept: "application/json",
        },
    });
}

export function httpPost(url, data) {
    return axios
        .post(url, data, {
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
            },
        })
}
